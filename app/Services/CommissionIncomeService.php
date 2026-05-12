<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\MiscellaneousTransaction;
use App\Models\Setting;

class CommissionIncomeService
{
    public function syncForLoan(Loan $loan): void
    {
        $adminFeePercent = (float) ($loan->admin_fee ?? 0);
        $loanAmount = (float) ($loan->amount ?? 0);
        $adminFeeValue = ($loanAmount * $adminFeePercent) / 100;
        $commissionRate = (float) (Setting::where('key', 'commission_income_rate')->value('value') ?? 20);

        $existingRecords = MiscellaneousTransaction::query()
            ->where('type', 'revenue')
            ->where('category', 'Commission Income')
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
            'type' => 'revenue',
            'category' => 'Commission Income',
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
        ];

        if ($primary) {
            $primary->update($payload);
        } else {
            $primary = MiscellaneousTransaction::create($payload);
        }

        $existingRecords
            ->filter(fn (MiscellaneousTransaction $record) => $record->id !== $primary->id)
            ->each(fn (MiscellaneousTransaction $record) => $record->delete());
    }
}
