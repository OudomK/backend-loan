<?php

namespace App\Http\Resources;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ArrearReportResource extends JsonResource
{
    /**
     * @var array<string, float>|null
     */
    private static ?array $penaltyRates = null;

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
        $aging = $arrearDate ? abs($referenceDate->diffInDays(Carbon::parse($arrearDate))) : 0;
        $arrearPrincipal = (float) ($this->arrear_principal ?? 0);
        $arrearInterest = (float) ($this->arrear_interest ?? 0);
        $totalArrearDue = round($arrearPrincipal + $arrearInterest, 2);
        $penaltyPaid = (float) ($this->penalty_paid_total ?? 0);
        if (!$this->late_since_date) {
            $penaltyDue = (float)($this->accumulated_penalty ?? 0);
        } else {
            $penaltyGross = 0;
            if ($aging > 0) {
                $penaltyGross = round($aging * $this->resolvePenaltyRate($this->resource), 2);
            }
            $penaltyDue = max(0, $penaltyGross - $penaltyPaid);
        }

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
            'name' => $borrower ? trim($borrower->first_name . ' ' . $borrower->last_name) : '-',
            'coborrower' => $this->coBorrower ? $this->coBorrower->first_name . ' ' . $this->coBorrower->last_name : '-',
            'guarantor' => $this->guarantor ? $this->guarantor->first_name . ' ' . $this->guarantor->last_name : '-',
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
            'arrear_fee' => 0,
            'penalty_due' => $penaltyDue,
            'penalty_paid' => $penaltyPaid,
            'status' => $status,
            'currency' => $this->currency,
        ];
    }

    /**
     * Resolve the penalty rate from the loan or settings.
     */
    private function resolvePenaltyRate(\App\Models\Loan $loan): float
    {
        if ($loan->penalty_rate !== null) {
            return (float) $loan->penalty_rate;
        }

        if (self::$penaltyRates === null) {
            $settings = Setting::whereIn('key', ['default_penalty_usd', 'default_penalty_khr'])
                ->pluck('value', 'key')
                ->toArray();

            self::$penaltyRates = [
                'USD' => (float) ($settings['default_penalty_usd'] ?? 2.5),
                'KHR' => (float) ($settings['default_penalty_khr'] ?? 10000),
            ];
        }

        return $loan->currency === 'KHR'
            ? self::$penaltyRates['KHR']
            : self::$penaltyRates['USD'];
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
