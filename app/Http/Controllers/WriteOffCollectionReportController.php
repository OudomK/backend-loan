<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\Excel\WriteOffCollectionExcelExport;

class WriteOffCollectionReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDateInput = $request->query('from_date');
        $toDateInput = $request->query('to_date');
        $currency = $request->query('currency', 'all');

        $fromDate = $fromDateInput
            ? Carbon::parse($fromDateInput)->startOfDay()
            : Carbon::today()->startOfMonth()->startOfDay();
        $toDate = $toDateInput
            ? Carbon::parse($toDateInput)->endOfDay()
            : Carbon::today()->endOfDay();

        $fromDateStr = $fromDate->toDateString();
        $toDateStr = $toDate->toDateString();
        $toDateTime = $toDate->toDateTimeString();

        $query = Loan::with([
            'borrower',
            'coBorrower',
            'guarantor',
            'officer',
            'collaterals',
            'payments' => function ($query) {
                $query->orderBy('payment_date', 'asc');
            },
            'transactions' => function ($query) use ($toDateTime) {
                $query->where('transaction_date', '<=', $toDateTime)
                    ->orderBy('transaction_date', 'asc');
            },
        ])
            ->where('status', '!=', 'pending')
            ->whereDate('start_date', '>=', $fromDateStr)
            ->whereDate('start_date', '<=', $toDateStr);

        if ($currency && $currency !== 'all') {
            $query->where('currency', $currency);
        }

        $loans = $query
            ->orderBy('start_date')
            ->orderBy('loan_code')
            ->get()
            ->map(function ($loan) use ($toDate, $toDateStr) {
                $borrower = $loan->borrower;
                $borrowerName = $borrower
                    ? trim((string) (($borrower->last_name ?? '') . ' ' . ($borrower->first_name ?? '')))
                    : '';

                $co = $loan->coBorrower;
                $coName = $co
                    ? trim((string) (($co->last_name ?? '') . ' ' . ($co->first_name ?? '')))
                    : '';

                $guarantor = $loan->guarantor;
                $guarantorName = $guarantor
                    ? trim((string) (($guarantor->last_name ?? '') . ' ' . ($guarantor->first_name ?? '')))
                    : '';

                $transactionsAtDate = $loan->transactions;
                $principalPaid = $transactionsAtDate->sum(function ($transaction) {
                    return $this->principalComponent($transaction);
                });

                $scheduledPaidAtDate = $transactionsAtDate->sum(function ($transaction) {
                    return (float) ($transaction->fee_paid ?? 0)
                        + (float) ($transaction->interest_paid ?? 0)
                        + (float) ($transaction->principal_paid ?? 0)
                        + (float) ($transaction->prepayment_paid ?? 0)
                        + (float) ($transaction->paid_off_amount ?? 0)
                        - (float) ($transaction->withdrawn_prepayment ?? 0);
                });

                $recoveryAmount = max(0, $transactionsAtDate->sum(function ($transaction) {
                    return (float) ($transaction->recovery_amount ?? 0);
                }));

                $outstanding = max(0, (float) ($loan->amount ?? 0) - $principalPaid);

                $cumulativeDue = 0.0;
                $cumulativePrincipalDue = 0.0;
                $earliestArrearDate = null;
                $earliestPrincipalArrearDate = null;

                foreach ($loan->payments as $payment) {
                    if (($payment->payment_date ?? '') >= $toDateStr) {
                        continue;
                    }

                    $cumulativeDue += (float) ($payment->principal_amount ?? 0)
                        + (float) ($payment->interest_amount ?? 0)
                        + (float) ($payment->fee_amount ?? 0);
                    $cumulativePrincipalDue += (float) ($payment->principal_amount ?? 0);

                    // 1. Check total amount due vs total amount paid
                    if (($cumulativeDue - $scheduledPaidAtDate) > 0.01 && $earliestArrearDate === null) {
                        $earliestArrearDate = $payment->payment_date;
                    }

                    // 2. Fallback: Check if principal specifically is overdue
                    if (($cumulativePrincipalDue - $principalPaid) > 0.01 && $earliestPrincipalArrearDate === null) {
                        $earliestPrincipalArrearDate = $payment->payment_date;
                    }
                }

                // If no total arrear date was found, but there is a principal shortfall, use that instead
                $effectiveArrearDate = $earliestArrearDate ?? $earliestPrincipalArrearDate;

                $amountDefault = max(0, $cumulativePrincipalDue - $principalPaid);
                $aging = 0;

                if ($effectiveArrearDate) {
                    $aging = abs($toDate->copy()->startOfDay()->diffInDays(
                        Carbon::parse($effectiveArrearDate)->startOfDay()
                    ));
                }

                if ($aging <= 0 && $amountDefault > 0.01) {
                    $aging = 1;
                }

                $writtenOffAt = $loan->written_off_at
                    ? Carbon::parse($loan->written_off_at)->endOfDay()
                    : null;
                $isWrittenOffAtDate = $writtenOffAt !== null && $writtenOffAt->lte($toDate);

                if ($outstanding <= 0.01 && !$isWrittenOffAtDate) {
                    return null;
                }

                $writeOffAmount = (float) ($loan->write_off_balance ?? 0);
                if ($writeOffAmount <= 0.01) {
                    $writeOffAmount = $outstanding;
                }

                $defaultBalance = $isWrittenOffAtDate
                    ? max(0, $writeOffAmount - $recoveryAmount)
                    : $outstanding;
                $amountDefault = $isWrittenOffAtDate
                    ? max(0, $writeOffAmount)
                    : $amountDefault;

                $classification = $this->classificationLabel($aging, $isWrittenOffAtDate);
                $collateralType = $loan->collaterals->first()?->type ?? '';

                return [
                    'disb_date' => $loan->start_date ?? '',
                    'loan_code' => \App\Support\FormatHelper::formatLoanCode((string) ($loan->loan_code ?? '')),
                    'customer_code' => $borrower->customer_code ?? '',
                    'borrower_name' => $borrowerName,
                    'phone_number' => $borrower->phone ?? '',
                    'co_borrower' => $coName,
                    'guarantor' => $guarantorName,
                    'village' => $borrower->village ?? '',
                    'commune' => $borrower->commune ?? '',
                    'district' => $borrower->district ?? '',
                    'province' => $borrower->province ?? '',
                    'collateral_type' => $collateralType,
                    'co_repay' => $loan->officer->name ?? '',
                    'maturity_date' => $loan->maturity_date ?? ($loan->payments->last()?->payment_date ?? ''),
                    'currency' => $loan->currency,
                    'term' => (int) ($loan->duration_months ?? 0),
                    'amount' => (float) ($loan->amount ?? 0),
                    'amount_default' => $amountDefault,
                    'default_balance' => $defaultBalance,
                    'recovery_amount' => $recoveryAmount,
                    'aging' => $aging,
                    'classification' => $classification,
                ];
            })
            ->filter()
            ->values();

        $categories = [
            'Standard Loan' => [],
            'Special Mention Loan' => [],
            'Substandard Loan' => [],
            'Doubtful Loan' => [],
            'Loss Loan' => [],
        ];

        foreach ($loans->groupBy('classification') as $classification => $items) {
            $categories[$classification] = $items->values()->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    private function principalComponent(mixed $transaction): float
    {
        return (float) ($transaction->principal_paid ?? 0)
            + (float) ($transaction->prepayment_paid ?? 0)
            + (float) ($transaction->paid_off_amount ?? 0)
            - (float) ($transaction->withdrawn_prepayment ?? 0);
    }

    private function classificationLabel(int $aging, bool $isWrittenOffAtDate): string
    {
        if ($isWrittenOffAtDate || $aging >= 360) {
            return 'Loss Loan';
        }
        if ($aging >= 180) {
            return 'Doubtful Loan';
        }
        if ($aging >= 90) {
            return 'Substandard Loan';
        }
        if ($aging >= 30) {
            return 'Special Mention Loan';
        }

        return 'Standard Loan';
    }

    public function exportExcel(Request $request)
    {
        $response = $this->index($request);
        $data = json_decode($response->getContent(), true);

        $fromDateInput = $request->query('from_date');
        $toDateInput = $request->query('to_date');
        $currency = $request->query('currency', 'all');

        $fromDateStr = $fromDateInput ?? Carbon::today()->startOfMonth()->toDateString();
        $toDateStr = $toDateInput ?? Carbon::today()->toDateString();

        $export = new WriteOffCollectionExcelExport();
        return $export->download($data['data'] ?? [], $request, $fromDateStr, $toDateStr, $currency);
    }
}
