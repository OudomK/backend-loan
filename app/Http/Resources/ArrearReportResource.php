<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArrearReportResource extends JsonResource
{
    /**
     * Transform one unpaid installment into an arrears report row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Payment $this */
        $loan = $this->loan;
        $borrower = $loan?->borrower;
        $referenceDateStr = $request->query('to_date') ?? $request->query('report_date');
        $referenceDate = ($referenceDateStr ? Carbon::parse($referenceDateStr) : Carbon::today())->startOfDay();
        $arrearDate = Carbon::parse($this->payment_date)->startOfDay();

        $feeAmount = (float) ($this->fee_amount ?? 0);
        $feePaid = (float) ($this->fee_paid ?? 0);
        $amountPaidAfterFee = max(0, (float) $this->total_paid - $feePaid);
        $interestPaid = min((float) $this->interest_amount, $amountPaidAfterFee);
        $principalPaid = max(0, $amountPaidAfterFee - (float) $this->interest_amount);

        $arrearPrincipal = round(max(0, (float) $this->principal_amount - $principalPaid), 2);
        $arrearInterest = round(max(0, (float) $this->interest_amount - $interestPaid), 2);
        $arrearFee = round(max(0, $feeAmount - $feePaid), 2);
        $aging = $referenceDate->gt($arrearDate)
            ? (int) abs($referenceDate->diffInDays($arrearDate))
            : 0;

        $status = ((float) $this->total_paid > 0.001 || $feePaid > 0.001)
            ? 'Partial'
            : 'Active';

        return [
            // Kept in the payload so totals can de-duplicate loan-level values.
            'loan_id' => $loan?->id,
            'payment_id' => $this->id,
            'installment_no' => $this->payment_number,
            'branches' => 'Main Office',
            'arrear_date' => $this->payment_date,
            'loan_no' => $loan?->loan_code ?? '-',
            'cid' => $borrower?->customer_code ?? '-',
            'name' => $borrower ? trim($borrower->first_name.' '.$borrower->last_name) : '-',
            'coborrower' => $loan?->coBorrower ? $loan->coBorrower->first_name.' '.$loan->coBorrower->last_name : '-',
            'guarantor' => $loan?->guarantor ? $loan->guarantor->first_name.' '.$loan->guarantor->last_name : '-',
            'gender' => $borrower?->formatted_gender ?: '-',
            'phone' => $borrower?->phone ?? '-',
            'coborrower_phone' => $loan?->coBorrower?->phone ?? '-',
            'guarantor_phone' => $loan?->guarantor?->phone ?? '-',
            'co' => $loan?->officer?->name ?? '-',
            'village' => $borrower?->village ?? '-',
            'commune' => $borrower?->commune ?? '-',
            'last_payment_date' => $this->last_transaction_date ?? '-',
            'aging' => $aging,
            'types_of_collateral' => $this->getCollateralTypeLabel(),
            'number' => $loan?->collaterals->count() ?? 0,
            'date_disbursement' => $loan?->start_date,
            'disb_amount' => (float) ($loan?->amount ?? 0),
            'outstanding' => (float) ($this->calculated_outstanding ?? 0),
            // Arrear Amount is the scheduled principal and interest still due.
            // Interest remains available separately for report breakdowns.
            'arrear_amount' => round($arrearPrincipal + $arrearInterest, 2),
            'arrear_interest' => $arrearInterest,
            'arrear_fee' => $arrearFee,
            // The controller attaches loan-level penalty to the oldest visible
            // unpaid installment only, preventing duplicate report totals.
            'penalty_due' => 0.0,
            'penalty_paid' => 0.0,
            'status' => $status,
            'currency' => $loan?->currency ?? 'USD',
        ];
    }

    private function getCollateralTypeLabel(): string
    {
        $first = $this->loan?->collaterals->first();
        if (! $first) {
            return '-';
        }

        $type = trim((string) $first->type);
        if ($type === '') {
            return '-';
        }

        if (is_numeric($type)) {
            $description = trim((string) ($first->description ?? ''));

            return $description !== '' ? $description : '-';
        }

        return $type;
    }
}
