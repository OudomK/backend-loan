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
            'loan_no' => $loan->loan_code,
            'cid' => $borrower->customer_code,
            'name' => $borrower->last_name . ' ' . $borrower->first_name,
            'village' => $borrower->village,
            'commune' => $borrower->commune,
            'district' => $borrower->district,
            'province' => $borrower->province,

            'coborrower_name' => $loan->coBorrower ? $loan->coBorrower->last_name . ' ' . $loan->coBorrower->first_name : null,
            'coborrower_tel' => $loan->coBorrower?->phone,
            'guarantor_name' => $loan->guarantor ? $loan->guarantor->last_name . ' ' . $loan->guarantor->first_name : null,
            'guarantor_tel' => $loan->guarantor?->phone,

            'disb_amount' => $loan->amount,
            'currency' => $loan->currency,
            'rate' => $loan->interest_rate,
            'processing_fee' => 0,
            'monthly_interest' => (float) $loan->monthly_interest,
            'term' => $loan->duration_months,
            'tenor' => $loan->duration_months,
            'payment_frequency' => $loan->payment_frequency,
            'payment_method' => $loan->repayment_method,
            'loan_cycle' => $loan->loan_cycle,
            're_finance' => $loan->refinanced_amount,
            'admin_fee' => $loan->admin_fee,
            're_finance_fee' => $loan->refinance_fee,
            'reschedule_fee' => $loan->reschedule_fee,
            'collateral_type' => $this->getCollateralTypeLabel($loan),
            'co_disburse' => $loan->officer?->name,
            'co_repay' => $this->collector?->name ?? $loan->officer?->name,

            'principal_paid' => $this->principal_paid,
            'interest_paid' => $this->interest_paid,
            'penalty_paid' => $this->penalty_paid,
            'paid_off_paid' => (float) $this->paid_off_amount,
            'recovery' => (float) $this->recovery_amount,
            'prepayment' => (float) $this->prepayment_paid,
            'withd_prepayment' => (float) $this->withdrawn_prepayment,
            // Total Paid = reflects actual cash collected; Subtract withdrawals from total
            'total_paid' => $this->repayment_type === 'Withdraw' ? -(float) $this->amount_paid : (float) $this->amount_paid,
            'type_of_payment' => $this->repayment_type,
            'payment_status' => $loan->status,
            'fee_paid' => (float) $this->fee_paid,
            'total_paid_usd' => $this->calculateUsdValue($loan, $this->repayment_type === 'Withdraw' ? -(float) $this->amount_paid : (float) $this->amount_paid),
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

    private function calculateUsdValue($loan, $amount): float
    {
        if (str_contains(strtoupper($loan->currency), 'USD')) {
            return (float) $amount;
        }

        static $rate = null;
        if ($rate === null) {
            $rate = (float) (\App\Models\Setting::where('key', 'exchange_rate_khr_to_usd')->value('value') ?: 4000);
        }

        return $rate > 0 ? (float) ($amount / $rate) : (float) $amount;
    }

    /**
     * Show collateral type (text). If type looks like a number (e.g. value stored in type by mistake), use description or null.
     */
    private function getCollateralTypeLabel($loan): ?string
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
}
