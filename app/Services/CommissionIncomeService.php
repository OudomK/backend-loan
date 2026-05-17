<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Models\Setting;
use App\Models\MiscellaneousTransaction;

class CommissionIncomeService
{
    public function syncForLoan(Loan $loan): void
    {
        $adminFeePercent = (float) ($loan->admin_fee ?? 0);
        $loanAmount = (float) ($loan->amount ?? 0);
        $adminFeeValue = ($loanAmount * $adminFeePercent) / 100;
        $commissionRate = (float) (Setting::where('key', 'commission_income_rate')->value('value') ?? 20);

        // Find the RevenueCategory ID for Commission Income
        $category = RevenueCategory::where('name', 'Commission Income')->first();
        if (!$category) {
            return; // Or handle error
        }

        // Clean up old MiscellaneousTransaction records if any exist (to avoid duplication during migration)
        MiscellaneousTransaction::where('type', 'revenue')
            ->where('category', 'Commission Income')
            ->where('loan_id', $loan->id)
            ->delete();

        $existingRecords = Revenue::query()
            ->where('revenue_category_id', $category->id)
            ->where('loan_id', $loan->id)
            ->orderByDesc('id')
            ->get();
        $primary = $existingRecords->first();

        if ($adminFeeValue <= 0 || $commissionRate <= 0) {
            foreach ($existingRecords as $record) {
                $record->delete();
            }
            return;
        }

        $commissionAmount = round(($adminFeeValue * $commissionRate) / 100, 2);
        if ($commissionAmount <= 0) {
            foreach ($existingRecords as $record) {
                $record->delete();
            }
            return;
        }

        $payload = [
            'revenue_category_id' => $category->id,
            'loan_id' => $loan->id,
            'amount' => $commissionAmount,
            'currency' => $loan->currency ?? 'USD',
            'transaction_date' => $loan->start_date ?? now()->toDateString(),
            'description' => sprintf(
                'Auto commission from loan %s at %s%% of admin fee %s',
                $loan->loan_code ?: ('Loan #' . $loan->id),
                rtrim(rtrim(number_format($commissionRate, 2, '.', ''), '0'), '.'),
                number_format($adminFeeValue, 2, '.', '')
            ),
            'status' => 'completed',
        ];

        if ($primary) {
            $primary->update($payload);
        } else {
            $primary = Revenue::create($payload);
        }

        $existingRecords
            ->filter(fn (Revenue $record) => $record->id !== $primary->id)
            ->each(fn (Revenue $record) => $record->delete());
    }
}
