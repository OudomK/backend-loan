<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

        $arrearDate = $this->calculated_late_since_date ?? $this->late_since_date;
        $arrearPrincipal = (float) ($this->arrear_principal ?? 0);
        $arrearInterest = (float) ($this->arrear_interest ?? 0);
        $arrearFee = (float) ($this->arrear_fee ?? 0);
        $totalArrearDue = round($arrearPrincipal + $arrearInterest + $arrearFee, 2);
        $aging = $arrearDate && Carbon::parse($arrearDate)->isSameDay($referenceDate)
            ? 0
            : $this->agingAt($referenceDate, $arrearDate, $totalArrearDue > 0.01);
        $penaltyPaid = (float) ($this->penalty_paid_total ?? 0);
        $penaltyDue = $this->currentPenaltyDue($referenceDate);

        $status = 'Active';
        if ($totalArrearDue <= 0.01) {
            $status = 'OK';
        } elseif (! empty($this->last_transaction_date) && $this->last_transaction_date !== '-') {
            $status = 'Partial';
        }

        return [
            'branches' => 'Main Office',
            'arrear_date' => $arrearDate,
            'loan_no' => $this->loan_code,
            'cid' => $borrower?->customer_code ?? '-',
            'name' => $borrower ? trim($borrower->first_name.' '.$borrower->last_name) : '-',
            'coborrower' => $this->coBorrower ? $this->coBorrower->first_name.' '.$this->coBorrower->last_name : '-',
            'guarantor' => $this->guarantor ? $this->guarantor->first_name.' '.$this->guarantor->last_name : '-',
            'gender' => $borrower?->gender ?? '-',
            'phone' => $borrower?->phone ?? '-',
            'coborrower_phone' => $this->coBorrower?->phone ?? '-',
            'guarantor_phone' => $this->guarantor?->phone ?? '-',
            'co' => $this->officer?->name ?? '-',
            'village' => $borrower?->village ?? '-',
            'commune' => $borrower?->commune ?? '-',
            'last_payment_date' => $this->last_transaction_date ?? '-',
            'aging' => $aging,
            'types_of_collateral' => $this->getCollateralTypeLabel(),
            'number' => $this->collaterals->count(),
            'date_disbursement' => $this->start_date,
            'disb_amount' => $this->amount,
            'outstanding' => $this->calculated_outstanding ?? 0,
            'arrear_amount' => $arrearPrincipal,
            'arrear_interest' => $arrearInterest,
            'arrear_fee' => $arrearFee,
            'penalty_due' => $penaltyDue,
            'penalty_paid' => $penaltyPaid,
            'status' => $status,
            'currency' => $this->currency,
        ];
    }

    /**
     * Collateral type label. If type is numeric (e.g. value stored by mistake), use description or '-'.
     */
    private function getCollateralTypeLabel(): string
    {
        $first = $this->collaterals->first();
        if (! $first) {
            return '-';
        }
        $type = trim((string) $first->type);
        if ($type === '') {
            return '-';
        }
        if (is_numeric($type)) {
            $desc = ! empty(trim((string) ($first->description ?? ''))) ? trim($first->description) : null;

            return $desc ?? '-';
        }

        return $type;
    }
}
