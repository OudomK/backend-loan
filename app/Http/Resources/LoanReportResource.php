<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\RepaymentTransaction $this */
        $loan = $this->loan;
        $borrower = $loan->borrower;

        return [
            'payment_date' => $this->transaction_date,
            'receipt_no' => $this->id,
            'disb_date' => $loan->start_date,
            'loan_no' => $this->formatLoanCode($loan->loan_code),
            'cid' => $borrower->customer_code,
            'name' => $borrower->first_name . ' ' . $borrower->last_name,
            'village' => $borrower->village,
            'commune' => $borrower->commune,
            'district' => $borrower->district,
            'province' => $borrower->province,

            'coborrower_name' => $loan->coBorrower ? $loan->coBorrower->first_name . ' ' . $loan->coBorrower->last_name : null,
            'coborrower_tel' => $loan->coBorrower?->phone,
            'guarantor_name' => $loan->guarantor ? $loan->guarantor->first_name . ' ' . $loan->guarantor->last_name : null,
            'guarantor_tel' => $loan->guarantor?->phone,

            'disb_amount' => $loan->amount,
            'currency' => $loan->currency,
            'rate' => $loan->interest_rate,
            'interest_rate' => $loan->interest_rate,
            'processing_fee' => 0,
            'monthly_interest' => (float) $loan->monthly_interest,
            'monthly_interest_rate' => (float) $loan->monthly_interest,
            'term' => $loan->duration_months,
            'tenor' => $this->tenorLabel($loan->payment_frequency),
            'payment_frequency' => ucfirst((string) $loan->payment_frequency),
            'payment_method' => $this->formatPaymentMethod($loan->repayment_method),
            'loan_cycle' => $loan->loan_cycle,
            're_finance' => $loan->refinanced_amount,
            'admin_fee' => $loan->admin_fee,
            're_finance_fee' => $loan->refinance_fee,
            'reschedule_fee' => $loan->reschedule_fee,
            'collateral_type' => $this->getCollateralTypeLabel($loan),
            'product_name' => $loan->product?->name,
            'co_disburse' => $loan->officer?->name,
            'co_repay' => $this->collector?->name ?? $loan->officer?->name,

            'principal_paid' => $this->principal_paid,
            'interest_paid' => $this->interest_paid,
            'penalty_paid' => $this->penalty_paid,
            'paid_off_paid' => (float) $this->paid_off_amount,
            'recovery' => (float) $this->recovery_amount,
            'prepayment' => (float) $this->prepayment_paid,
            'withd_prepayment' => (float) $this->withdrawn_prepayment,
            // Total Paid = actual cash collected across principal/interest amount + penalty + fees.
            'total_paid' => $this->actualCollectedAmount(),
            'type_of_payment' => $this->repayment_type,
            'payment_status' => $loan->status,
            'fee_paid' => (float) $this->fee_paid,
            'total_paid_usd' => $this->calculateUsdValue($loan, $this->actualCollectedAmount()),
            'principal_paid_usd' => $this->calculateUsdValue($loan, $this->principal_paid),
            'interest_paid_usd' => $this->calculateUsdValue($loan, $this->interest_paid),
            'penalty_paid_usd' => $this->calculateUsdValue($loan, $this->penalty_paid),
            'recovery_usd' => $this->calculateUsdValue($loan, $this->recovery_amount),
            'prepayment_usd' => $this->calculateUsdValue($loan, $this->prepayment_paid),
            'withd_prepayment_usd' => $this->calculateUsdValue($loan, $this->withdrawn_prepayment),
            'paid_off_paid_usd' => $this->calculateUsdValue($loan, $this->paid_off_amount),
            'fee_paid_usd' => $this->calculateUsdValue($loan, (float) $this->fee_paid),
        ];
    }

    private function calculateUsdValue(\App\Models\Loan $loan, mixed $amount): float
    {
        if (str_contains(strtoupper($loan->currency), 'USD')) {
            return (float) $amount;
        }

        static $rate = null;
        if ($rate === null) {
            $rate = (float) (\App\Models\Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
                ?? \App\Models\Setting::where('key', 'exchange_rate')->value('value')
                ?? 4000);
        }

        return $rate > 0 ? (float) ($amount / $rate) : (float) $amount;
    }

    private function actualCollectedAmount(): float
    {
        $base = (float) $this->amount_paid;

        if ($this->repayment_type === 'Withdraw') {
            return -$base;
        }

        return $base
            + (float) $this->penalty_paid
            + (float) $this->fee_paid;
    }

    /**
     * Show collateral type (text). If type looks like a number (e.g. value stored in type by mistake), use description or null.
     */
    private function getCollateralTypeLabel(\App\Models\Loan $loan): ?string
    {
        $first = $loan->collaterals->first();
        if (!$first) {
            return null;
        }
        $type = trim((string) $first->type);
        if ($type === '') {
            return null;
        }
        if (is_numeric($type)) {
            return !empty(trim((string) ($first->description ?? ''))) ? trim($first->description) : null;
        }
        return $type;
    }

    private function formatPaymentMethod(?string $method): string
    {
        return \App\Support\FormatHelper::formatPaymentMethod($method);
    }

    private function tenorLabel(?string $paymentFrequency): string
    {
        $normalized = strtolower(trim((string) $paymentFrequency));
        if ($normalized === 'monthly' || $normalized === 'month') {
            return 'ខែ';
        }
        return 'ដង';
    }

    private function formatLoanCode(?string $loanCode): ?string
    {
        return \App\Support\FormatHelper::formatLoanCode($loanCode);
    }
}
