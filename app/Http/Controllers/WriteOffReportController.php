<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\Excel\WriteOffExcelExport;
use Illuminate\Support\Facades\Log;

class WriteOffReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDateInput = $request->query('from_date');
        $toDateInput = $request->query('to_date');
        $currency = $request->query('currency', 'all');

        $toDate = $toDateInput ? Carbon::parse($toDateInput)->endOfDay() : Carbon::today()->endOfDay();
        $fromDate = $fromDateInput
            ? Carbon::parse($fromDateInput)->startOfDay()
            : $toDate->copy()->startOfMonth()->startOfDay();

        $fromDateOnly = $fromDate->toDateString();
        $toDateOnly = $toDate->toDateString();
        $toDateTime = $toDate->toDateTimeString();

        $query = Loan::with([
            'borrower',
            'officer',
            'disburseOfficer',
            'collaterals',
            'product',
            'transactions' => function ($query) use ($toDateTime) {
                $query->where('transaction_date', '<=', $toDateTime)
                    ->orderBy('transaction_date', 'asc');
            },
        ])
            ->whereNotNull('written_off_at')
            ->whereDate('written_off_at', '>=', $fromDateOnly)
            ->whereDate('written_off_at', '<=', $toDateOnly);

        if ($currency !== 'all') {
            $query->where('currency', $currency);
        }

        $loans = $query
            ->orderBy('written_off_at')
            ->orderBy('loan_code')
            ->get();

        $reportData = [];

        foreach ($loans as $loan) {
            try {
                $writtenOffAt = Carbon::parse($loan->written_off_at)->endOfDay();

                $preWriteOffTransactions = $loan->transactions->filter(function ($transaction) use ($writtenOffAt) {
                    return Carbon::parse($transaction->transaction_date)->lte($writtenOffAt)
                        && strcasecmp((string) ($transaction->repayment_type ?? ''), 'Recovery') !== 0;
                });

                $principalCollected = max(0, $preWriteOffTransactions->sum(function ($transaction) {
                    return $this->principalComponent($transaction);
                }));

                $interestCollected = max(0, $preWriteOffTransactions->sum(function ($transaction) {
                    return (float) ($transaction->interest_paid ?? 0);
                }));

                $recoveryAmount = max(0, $loan->transactions->sum(function ($transaction) {
                    return (float) ($transaction->recovery_amount ?? 0);
                }));

                $writeOffAmount = (float) ($loan->write_off_balance ?? 0);
                if ($writeOffAmount <= 0.01) {
                    $writeOffAmount = max(0, (float) $loan->amount - $principalCollected);
                }

                $currentWriteOffBalance = max(0, $writeOffAmount - $recoveryAmount);
                $borrowerName = trim((string) (($loan->borrower->last_name ?? '') . ' ' . ($loan->borrower->first_name ?? '')));

                $reportData[] = [
                    'written_off_date' => $loan->written_off_at,
                    'disbursement_date' => $loan->start_date ?? '',
                    'loan_code' => \App\Support\FormatHelper::formatLoanCode((string) ($loan->loan_code ?? '')),
                    'product_name' => $loan->product->name ?? 'General Loan',
                    'customer_code' => $loan->borrower->customer_code ?? '',
                    'customer_name' => $borrowerName,
                    'village' => $loan->borrower->village ?? '',
                    'commune' => $loan->borrower->commune ?? '',
                    'district' => $loan->borrower->district ?? '',
                    'province' => $loan->borrower->province ?? '',
                    'amount' => (float) ($loan->amount ?? 0),
                    'currency' => $loan->currency,
                    'rate' => (float) ($loan->interest_rate ?? 0),
                    'monthly_interest_rate' => (float) ($loan->monthly_interest ?? ((float) ($loan->interest_rate ?? 0) / 12)),
                    'term' => (int) ($loan->duration_months ?? 0),
                    'tenor' => $this->tenorLabel($loan->payment_frequency),
                    'payment_method' => \App\Support\FormatHelper::formatPaymentMethod((string) ($loan->repayment_method ?? '')),
                    'loan_cycle' => (int) ($loan->loan_cycle ?? 1),
                    'refinance_fee' => (float) ($loan->refinance_fee ?? 0),
                    'admin_fee' => (float) ($loan->admin_fee ?? 0),
                    'restructure_fee' => (float) ($loan->reschedule_fee ?? 0),
                    'collateral_type' => $loan->collaterals->isNotEmpty() ? ($loan->collaterals->first()->type ?? '') : '',
                    'co_disburse' => $loan->disburseOfficer->name ?? ($loan->officer->name ?? ''),
                    'co_repay' => $loan->officer->name ?? '',
                    'amount_write_off' => $writeOffAmount,
                    'write_off_balance' => $currentWriteOffBalance,
                    'principal_collected' => $principalCollected,
                    'interest_collected' => $interestCollected,
                    'recovery_amount' => $recoveryAmount,
                    'maturity_date' => $loan->maturity_date,
                    'write_off_reason' => $loan->write_off_reason ?? '',
                    'status' => $loan->status ?? 'written_off',
                    'classify_wo' => $loan->classify_wo ?? '',
                ];
            } catch (\Throwable $e) {
                Log::error("WriteOffReport Error for Loan {$loan->id}: {$e->getMessage()}");
            }
        }

        return response()->json($reportData);
    }

    private function principalComponent(mixed $transaction): float
    {
        return (float) ($transaction->principal_paid ?? 0)
            + (float) ($transaction->prepayment_paid ?? 0)
            + (float) ($transaction->paid_off_amount ?? 0)
            - (float) ($transaction->withdrawn_prepayment ?? 0);
    }

    private function tenorLabel(?string $paymentFrequency): string
    {
        $normalized = strtolower(trim((string) $paymentFrequency));

        return match ($normalized) {
            'monthly' => 'Months',
            'biweekly' => 'Bi-weekly',
            'weekly' => 'Weeks',
            'daily' => 'Days',
            'bi-monthly', 'bimonthly', 'semi-monthly' => 'Semi-Monthly',
            default => $normalized !== '' ? ucwords(str_replace(['_', '-'], ' ', $normalized)) : '',
        };
    }

    public function exportExcel(Request $request)
    {
        $response = $this->index($request);
        $data = json_decode($response->getContent(), true);

        $fromDateInput = $request->query('from_date');
        $toDateInput = $request->query('to_date');
        $currency = $request->query('currency', 'all');

        $fromDate = $fromDateInput ? Carbon::parse($fromDateInput) : Carbon::today()->startOfMonth();
        $toDate = $toDateInput ? Carbon::parse($toDateInput) : Carbon::today();

        $export = new WriteOffExcelExport();
        return $export->download($data, $request, $fromDate->format('d-M-y'), $toDate->format('d-M-y'), $currency);
    }
}
