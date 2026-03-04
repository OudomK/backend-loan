<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ArrearReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Loan $this */
        $borrower = $this->borrower;
        $reportDateStr = $request->query('report_date');
        $referenceDate = $reportDateStr ? Carbon::parse($reportDateStr) : Carbon::today();

        $arrearDate = $this->earliest_arrear_date;
        $aging = $arrearDate ? $referenceDate->diffInDays(Carbon::parse($arrearDate)) : 0;

        return [
            'branches' => 'Main Office',
            'arrear_date' => $arrearDate,
            'loan_no' => $this->loan_code,
            'cid' => $borrower->customer_code,
            'name' => $borrower->first_name . ' ' . $borrower->last_name,
            'coborrower' => $this->coBorrower ? $this->coBorrower->first_name . ' ' . $this->coBorrower->last_name : '-',
            'guarantor' => $this->guarantor ? $this->guarantor->first_name . ' ' . $this->guarantor->last_name : '-',
            'gender' => $borrower->gender,
            'phone' => $borrower->phone,
            'coborrower_phone' => $this->coBorrower?->phone ?? '-',
            'guarantor_phone' => $this->guarantor?->phone ?? '-',
            'co' => $this->officer?->name ?? '-',
            'village' => $borrower->village,
            'commune' => $borrower->commune,
            'last_payment_date' => $this->last_transaction_date ?? '-',
            'aging' => $aging,
            'types_of_collateral' => $this->collaterals->first()?->type ?? '-',
            'number' => $this->collaterals->count(),
            'date_disbursement' => $this->start_date,
            'disb_amount' => $this->amount,
            'outstanding' => $this->calculated_outstanding ?? 0,
            'arrear_amount' => $this->arrear_principal ?? 0,
            'arrear_interest' => $this->arrear_interest ?? 0,
            'arrear_fee' => 0,
            'penalty' => $this->arrear_penalty ?? 0,
            'prepayment' => 0,
            'status' => $this->status,
            'currency' => $this->currency,
        ];
    }
}
