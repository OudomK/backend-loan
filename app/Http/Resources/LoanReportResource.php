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
            'processing_fee' => 0,
            'monthly_interest_rate' => $loan->interest_rate,
            'term' => $loan->duration_months,
            'tenor' => $loan->duration_months,
            'payment_frequency' => $loan->payment_frequency,
            'payment_method' => $loan->repayment_method,
            'loan_cycle' => $loan->loan_cycle,
            're_finance' => $loan->refinanced_amount,
            'admin_fee' => $loan->admin_fee,
            're_finance_fee' => $loan->refinance_fee,
            'collateral_type' => $loan->collaterals->first()?->type,
            'co_disburse' => $loan->officer?->name,
            'co_repay' => $this->collector?->name ?? $loan->officer?->name,

            'principal_paid' => $this->principal_paid,
            'interest_paid' => $this->interest_paid,
            'penalty_paid' => $this->penalty_paid,
            'paid_off_paid' => $this->repayment_type === 'Pay Off' ? $this->principal_paid : 0,
            'recovery' => $this->repayment_type === 'Recovery' ? $this->amount_paid : 0,
            'prepayment' => $this->repayment_type === 'Prepayment' ? $this->amount_paid : 0,
            'withd_prepayment' => 0,
            'total_paid' => $this->amount_paid,
            'type_of_payment' => $this->repayment_type,
            'payment_status' => $loan->status,
            'fee_paid' => 0,
        ];
    }
}
