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
        $referenceDateStr = $request->query('to_date') ?? $request->query('report_date');
        $referenceDate = $referenceDateStr ? Carbon::parse($referenceDateStr) : Carbon::today();

        $arrearDate = $this->earliest_arrear_date;
        $aging = $arrearDate ? abs($referenceDate->diffInDays(Carbon::parse($arrearDate))) : 0;

        return [
            'branches' => 'Main Office',
            'arrear_date' => $arrearDate,
            'loan_no' => $this->loan_code,
            'cid' => $borrower->customer_code,
            'name' => $borrower->last_name . ' ' . $borrower->first_name,
            'coborrower' => $this->coBorrower ? $this->coBorrower->last_name . ' ' . $this->coBorrower->first_name : '-',
            'guarantor' => $this->guarantor ? $this->guarantor->last_name . ' ' . $this->guarantor->first_name : '-',
            'gender' => $borrower->gender,
            'phone' => $borrower->phone,
            'coborrower_phone' => $this->coBorrower?->phone ?? '-',
            'guarantor_phone' => $this->guarantor?->phone ?? '-',
            'co' => $this->officer?->name ?? '-',
            'village' => $borrower->village,
            'commune' => $borrower->commune,
            'last_payment_date' => $this->last_transaction_date ?? '-',
            'aging' => $aging,
            'types_of_collateral' => $this->getCollateralTypeLabel(),
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

    /**
     * Collateral type label. If type is numeric (e.g. value stored by mistake), use description or '-'.
     */
    private function getCollateralTypeLabel(): string
    {
        $first = $this->collaterals->first();
        if (!$first) {
            return '-';
        }
        $type = trim((string) $first->type);
        if ($type === '') {
            return '-';
        }
        if (is_numeric($type)) {
            $desc = !empty(trim((string) ($first->description ?? ''))) ? trim($first->description) : null;
            return $desc ?? '-';
        }
        return $type;
    }
}
