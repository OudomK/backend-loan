<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\CoBorrower;
use App\Models\Guarantor;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerHistoryController extends Controller
{
    /** 
     * Build payments array for frontend from payments table (schedule + total_paid per installment).
     * @param Loan $loan
     * @return array<string, mixed>
     */
    private function loanToHistoryArray(Loan $loan): array
    {
        $arr = $loan->toArray();

        $loan->loadMissing('payments.allocations');

        // Pre-load all repayment transactions for this loan in ONE query (fix N+1)
        $txIds = $loan->payments->pluck('repayment_transaction_id')->filter()->unique();
        $txMap = \App\Models\RepaymentTransaction::whereIn('id', $txIds)
            ->get()->keyBy('id');

        // Pre-load all transaction types for allocations as well
        $allocationTxIds = $loan->payments->flatMap->allocations->pluck('repayment_transaction_id')->filter()->unique();
        $allocationTxMap = \App\Models\RepaymentTransaction::whereIn('id', $allocationTxIds)
            ->get()->keyBy('id');

        $currentOS = $loan->getBasePrincipalForOS();
        $paymentsList = $loan->payments->sortBy('payment_number')->values();
        $lastPaymentId = $paymentsList->last() ? $paymentsList->last()->id : null;
        
        $groups = [];
        foreach ($paymentsList as $p) {
            if ((float)$p->total_paid > 0) {
                $key = $p->repayment_transaction_id ? (string)$p->repayment_transaction_id : ($p->updated_at ? (string)$p->updated_at : '');
                if ($key !== '') {
                    $groups[$key][] = $p->id;
                }
            }
        }

        $now = \Carbon\Carbon::now()->startOfDay();
        if ($loan->status === 'written_off' && $loan->written_off_at) {
            $now = \Carbon\Carbon::parse($loan->written_off_at)->startOfDay();
        }

        $arr['payments'] = $paymentsList->map(function(\App\Models\Payment $p) use ($txMap, $allocationTxMap, &$currentOS, $groups, $lastPaymentId, $now): array {
            return $this->mapPaymentToArray($p, $txMap, $allocationTxMap, $currentOS, $groups, $lastPaymentId, $now);
        })->all();

        // Calculate Summary Stats
        // $now is already defined and capped if written_off
        $totalPaidForLoan = 0.0;
        $totalPrincipalPaid = 0.0;
        $totalOverdueAmount = 0.0;
        $dynamicEarliestOverdue = null;
        
        foreach ($loan->payments as $p) {
            $totalPaidForLoan += (float)$p->total_paid + (float)$p->penalty_amount;
            
            if ($p->allocations->isNotEmpty()) {
                foreach ($p->allocations as $a) {
                    $totalPrincipalPaid += (float)$a->principal_applied;
                }
            } else if ($p->total_paid > 0) {
                $interestPaid = $p->total_paid >= $p->interest_amount ? $p->interest_amount : $p->total_paid;
                $principalPaid = $p->total_paid - $interestPaid;
                if ($principalPaid > 0) $totalPrincipalPaid += $principalPaid;
            }

            $due = $p->payment_date ? \Carbon\Carbon::parse($p->payment_date)->startOfDay() : null;
            if ($due) {
                $totalDue = (float)$p->principal_amount + (float)$p->interest_amount + (float)($p->fee_amount ?? 0);
                if ($due->lt($now) && $p->total_paid < ($totalDue - 0.01)) {
                    $totalOverdueAmount += ($totalDue - (float)$p->total_paid);
                    if ($dynamicEarliestOverdue === null || $due->lt($dynamicEarliestOverdue)) {
                        $dynamicEarliestOverdue = $due->copy();
                    }
                }
            }
        }

        $basePrincipalForOS = $loan->getBasePrincipalForOS();
        
        $osBalance = $basePrincipalForOS - $totalPrincipalPaid;
        if ($osBalance < 0.001) $osBalance = 0.0;

        $earliestOverdue = $dynamicEarliestOverdue;
        $daysOverdue = $loan->currentAging($now);
        if ($daysOverdue < 0) $daysOverdue = 0;

        $penaltyDue = $loan->currentPenaltyDue($now);
        $dateOverdueStr = $earliestOverdue ? $earliestOverdue->format('Y-m-d') : '';

        // Once every installment is settled, Overdue Amount correctly becomes
        // zero. If a penalty is still outstanding, keep showing the start of
        // the completed late period that produced the locked loan-level aging.
        if ($dateOverdueStr === '' && $penaltyDue > 0.01 && $daysOverdue > 0) {
            $dateOverdueStr = (string) ($loan->latestSettledLatePeriod()['start_date'] ?? '');
        }

        $arr['summary'] = [
            'total_paid' => $totalPaidForLoan,
            'os_balance' => $osBalance,
            'date_overdue' => $dateOverdueStr,
            'days_overdue' => $daysOverdue,
            'penalty_due' => $penaltyDue,
            'overdue_amount' => $totalOverdueAmount,
        ];

        // Add modifications to the history
        $arr['modifications'] = \App\Models\LoanModification::where('loan_id', $loan->id)
            ->latest()
            ->get()
            ->map(fn(\App\Models\LoanModification $m): array => [
                'id' => $m->id,
                'type' => $m->type,
                'old_data' => $m->old_data,
                'new_data' => $m->new_data,
                'notes' => $m->notes,
                'created_at' => $m->created_at->toIso8601String(),
            ])->all();
        return $arr;
    }

    private function mapPaymentToArray(
        \App\Models\Payment $p,
        \Illuminate\Support\Collection $txMap,
        \Illuminate\Support\Collection $allocationTxMap,
        float &$currentOS,
        array $groups,
        ?int $lastPaymentId,
        \Carbon\Carbon $now
    ): array {
        if ($p->outstanding_balance !== null && (float)$p->outstanding_balance >= 0) {
            $currentOS = (float) $p->outstanding_balance;
        } else {
            $currentOS -= (float) $p->principal_amount;
            if ($currentOS < 0.001) $currentOS = 0.0;
        }
        // Calculate balances
        $totalFeePaid = 0.0;
        $totalInterestPaid = 0.0;
        $totalPrincipalPaid = 0.0;
        
        if ($p->allocations->isNotEmpty()) {
            foreach ($p->allocations as $a) {
                $totalFeePaid += (float)$a->fee_applied;
                $totalInterestPaid += (float)$a->interest_applied;
                $totalPrincipalPaid += (float)$a->principal_applied;
            }
        } else {
            $totalFeePaid = $p->total_paid > ((float)($p->fee_amount ?? 0)) ? ((float)($p->fee_amount ?? 0)) : $p->total_paid;
            $paidExcludingFee = $p->total_paid - $totalFeePaid;
            if ($paidExcludingFee < 0) $paidExcludingFee = 0;
            $totalInterestPaid = $paidExcludingFee > $p->interest_amount ? $p->interest_amount : $paidExcludingFee;
            $totalPrincipalPaid = $paidExcludingFee - $totalInterestPaid;
            if ($totalPrincipalPaid < 0) $totalPrincipalPaid = 0;
        }
        
        $repaymentType = $p->repayment_transaction_id ? ($txMap[$p->repayment_transaction_id]?->repayment_type ?? null) : null;
        $isPayoffTrigger = $repaymentType === 'Pay Off';
        $groupKey = $p->repayment_transaction_id ? (string)$p->repayment_transaction_id : ($p->updated_at ? (string)$p->updated_at : '');
        if ($groupKey !== '' && isset($groups[$groupKey])) {
            if (in_array($lastPaymentId, $groups[$groupKey])) {
                $isPayoffTrigger = true;
            }
        }

        $displayInterest = max(0, (float)$p->interest_amount - $totalInterestPaid);
        $displayPrincipal = max(0, (float)$p->principal_amount - $totalPrincipalPaid);
        $displayFee = max(0, (float)($p->fee_amount ?? 0) - $totalFeePaid);
        $displayTotal = $displayPrincipal + $displayInterest + $displayFee;

        // If it's a payoff transaction, all remaining balances are waived/cleared.
        if ($isPayoffTrigger) {
            $displayInterest = 0.0;
            $displayPrincipal = 0.0;
            $displayFee = 0.0;
            $displayTotal = 0.0;
        }
        
        // Early payment logic
        $prepaymentValue = (float)($p->prepayment ?? 0);
        $isEarly = false;
        if ($p->total_paid > 0 && $p->payment_date) {
            $tDateStr = $p->repayment_transaction_id ? ($txMap[$p->repayment_transaction_id]?->transaction_date ?? null) : null;
            $earlyPayDate = $tDateStr ? \Carbon\Carbon::parse($tDateStr)->startOfDay() : ($p->updated_at ? \Carbon\Carbon::parse($p->updated_at)->startOfDay() : null);
            $dDateTemp = \Carbon\Carbon::parse($p->payment_date)->startOfDay();
            if ($earlyPayDate && $earlyPayDate->lt($dDateTemp)) {
                $isEarly = true;
            }
        }
        
        if ($isEarly) {
            $prepaymentValue += (float)$p->total_paid;
        }



        $requiredTotal = (float)($p->principal_amount + $p->interest_amount + ($p->fee_amount ?? 0));
        $isFullyPaid = (float)$p->total_paid >= ($requiredTotal - 0.01) || $isPayoffTrigger;

        $dDate = $p->payment_date ? \Carbon\Carbon::parse($p->payment_date)->startOfDay() : null;
        $tDateStr = $p->repayment_transaction_id ? ($txMap[$p->repayment_transaction_id]?->transaction_date ?? null) : null;
        $payDate = $tDateStr ? \Carbon\Carbon::parse($tDateStr)->startOfDay() : ($p->updated_at ? \Carbon\Carbon::parse($p->updated_at)->startOfDay() : null);
        $nDate = $now->copy()->startOfDay();

        $isOverdue = !$isFullyPaid && $dDate && $nDate->gt($dDate);

        $scheduleOnTimeLabel = "0";
        $paymentOnTimeLabel = "0";

        if ($isFullyPaid && $p->settled_days_variance !== null) {
            $scheduleOnTimeLabel = (string) ((int) $p->settled_days_variance);
            $paymentOnTimeLabel = $scheduleOnTimeLabel;
        } elseif (!$isFullyPaid && $isOverdue && $dDate) {
            $diff = (int) $dDate->diffInDays($nDate, false);
            $scheduleOnTimeLabel = "-$diff";
        } elseif ($isFullyPaid && $payDate && $dDate) {
            $diff = (int) $dDate->diffInDays($payDate, false);
            $scheduleOnTimeLabel = $diff > 0 ? "-$diff" : ($diff < 0 ? (string)abs($diff) : "0");
        }

        if ($p->total_paid > 0 && $payDate && $dDate) {
            $diff = (int) $dDate->diffInDays($payDate, false);
            $paymentOnTimeLabel = $diff > 0 ? "-$diff" : ($diff < 0 ? (string)abs($diff) : "0");
        }

        return [
            'id' => $p->id,
            'payment_number' => $p->payment_number,
            'principal_amount' => (float) $p->principal_amount,
            'interest_amount' => (float) $p->interest_amount,
            'balance_principal' => $displayPrincipal,
            'balance_interest' => $displayInterest,
            'display_total' => $displayTotal,
            'fee_amount' => (float) ($p->fee_amount ?? 0),
            'penalty_amount' => (float) $p->penalty_amount,
            'total_paid' => (float) $p->total_paid,
            'total_due' => (float) ($p->total_due ?? 0),
            'payment_date' => $p->payment_date,
            'payment_method' => $p->payment_method,
            'updated_at' => ($p->total_paid > 0 && $p->updated_at) ? $p->updated_at->toIso8601String() : '',
            'prepayment' => $prepaymentValue,
            'original_prepayment' => (float) ($p->prepayment ?? 0),
            'repayment_transaction_id' => $p->repayment_transaction_id,
            'repayment_type' => $repaymentType,
            'transaction_date' => $p->repayment_transaction_id ? ($txMap[$p->repayment_transaction_id]?->transaction_date ?? null) : null,
            'settled_at' => $p->settled_at,
            'settled_due_date' => $p->settled_due_date,
            'settled_days_variance' => $p->settled_days_variance,
            'settlement_source' => $p->settlement_source,
            'schedule_on_time_label' => $scheduleOnTimeLabel,
            'payment_on_time_label' => $paymentOnTimeLabel,
            'outstanding_balance' => $currentOS,
            'is_payoff_trigger' => $isPayoffTrigger,
            'required_total' => $requiredTotal,
            'is_fully_paid' => $isFullyPaid,
            'is_overdue' => $isOverdue,
            'total_installment_value' => (float)$p->total_paid > 0 ? min($requiredTotal, (float)$p->total_paid) : 0.0,
            'total_amount_due' => $isPayoffTrigger ? 0.0 : (abs($requiredTotal - (float)$p->total_paid) < 0.001 ? 0.0 : max(0.0, $requiredTotal - (float)$p->total_paid)),
            'show_small_row' => ((float)$p->total_paid > 0 || (float)$p->penalty_amount > 0) && (!$isFullyPaid || $p->allocations->count() > 1),
            'allocations' => $p->allocations->map(function($a) use ($allocationTxMap, $dDate, $isPayoffTrigger) {
                $allocationTransaction = $allocationTxMap->get($a->repayment_transaction_id);
                $allocRepaymentType = $allocationTransaction?->repayment_type;
                
                $allocOnTimeLabel = "0";
                if ($dDate) {
                    // The repayment transaction is the accounting source of truth.
                    // payment_allocations.transaction_date is absent on legacy rows,
                    // while created_at is only the technical insert timestamp and can
                    // be weeks later than the date the customer actually paid.
                    $allocDateStr = $allocationTransaction?->transaction_date
                        ?: $a->transaction_date
                        ?: $a->created_at;
                    $aDate = $allocDateStr ? \Carbon\Carbon::parse($allocDateStr)->startOfDay() : null;
                    if ($aDate) {
                        $diff = (int) $dDate->diffInDays($aDate, false);
                        $allocOnTimeLabel = $diff > 0 ? "-$diff" : ($diff < 0 ? (string)abs($diff) : "0");
                    }
                }

                return [
                    'id' => $a->id,
                    'repayment_transaction_id' => $a->repayment_transaction_id,
                    'amount_applied' => (float) $a->amount_applied,
                    'fee_applied' => (float) $a->fee_applied,
                    'interest_applied' => (float) $a->interest_applied,
                    'principal_applied' => (float) $a->principal_applied,
                    'penalty_applied' => (float) $a->penalty_applied,
                    'created_at' => $a->created_at->toIso8601String(),
                    'updated_at' => $a->updated_at->toIso8601String(),
                    'repayment_type' => $allocRepaymentType,
                    'transaction_date' => $allocationTransaction?->transaction_date
                        ?: $a->transaction_date,
                    'alloc_on_time_label' => $allocOnTimeLabel,
                    'alloc_total_installment' => (float)$a->principal_applied + (float)$a->interest_applied + (float)$a->fee_applied,
                ];
            })->all(),
        ];
    }
    /**
     * Search borrowers for Client History.
     *
     * Short numeric queries are treated as customer row/code numbers. Searching
     * phone and ID fragments that short creates many unrelated matches (for
     * example, "030" is a common ID prefix).
     */
    public function search(Request $request)
    {
        $query = preg_replace('/\s+/u', ' ', trim((string) $request->query('query', '')));
        if ($query === '') {
            return response()->json([]);
        }

        $query = mb_substr($query, 0, 100);
        $escapedQuery = $this->escapeLike($query);
        $contains = "%{$escapedQuery}%";
        $prefix = "{$escapedQuery}%";
        $compactQuery = preg_replace('/[^\pL\pN]+/u', '', $query);
        $shortNumeric = preg_match('/^\d{1,3}$/', $query) === 1;

        $builder = Borrower::query()->select([
            'id',
            'row_no',
            'customer_code',
            'first_name',
            'last_name',
            'latin_name',
            'nickname',
            'phone',
            'id_number',
            'village',
            'commune',
            'district',
            'province',
            'customer_type',
            'deleted_at',
        ]);

        if ($shortNumeric) {
            $number = (int) $query;
            $customerCode = 'QF-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $numericContains = '%' . $this->escapeLike($query) . '%';

            $builder
                ->where(function ($q) use ($numericContains) {
                    $q->where('customer_code', 'like', $numericContains)
                        ->orWhereRaw('CAST(row_no AS CHAR) LIKE ?', [$numericContains]);
                })
                ->orderByRaw(
                    'CASE WHEN customer_code = ? THEN 0 WHEN row_no = ? THEN 1 ELSE 2 END',
                    [$customerCode, $number]
                );
        } else {
            $nameForward = $this->fullNameSql('first_name', 'last_name');
            $nameReverse = $this->fullNameSql('last_name', 'first_name');
            $shouldSearchPrivateNumbers = mb_strlen($compactQuery) >= 4;

            $builder->where(function ($q) use (
                $contains,
                $compactQuery,
                $nameForward,
                $nameReverse,
                $shouldSearchPrivateNumbers
            ) {
                $q->where('customer_code', 'like', $contains)
                    ->orWhere('first_name', 'like', $contains)
                    ->orWhere('last_name', 'like', $contains)
                    ->orWhere('latin_name', 'like', $contains)
                    ->orWhere('nickname', 'like', $contains)
                    ->orWhereRaw("{$nameForward} LIKE ?", [$contains])
                    ->orWhereRaw("{$nameReverse} LIKE ?", [$contains]);

                if ($shouldSearchPrivateNumbers) {
                    $compactContains = '%' . $this->escapeLike($compactQuery) . '%';
                    $q->orWhereRaw("REPLACE(REPLACE(phone, ' ', ''), '-', '') LIKE ?", [$compactContains])
                        ->orWhereRaw("REPLACE(REPLACE(id_number, ' ', ''), '-', '') LIKE ?", [$compactContains]);
                }
            });

            $builder->orderByRaw(
                "CASE
                    WHEN customer_code = ? THEN 0
                    WHEN first_name = ? OR last_name = ? OR latin_name = ? OR nickname = ? THEN 1
                    WHEN customer_code LIKE ? THEN 2
                    WHEN first_name LIKE ? OR last_name LIKE ? OR latin_name LIKE ? OR nickname LIKE ? THEN 3
                    ELSE 4
                END",
                [$query, $query, $query, $query, $query, $prefix, $prefix, $prefix, $prefix, $prefix]
            );
        }

        $borrowers = $builder
            ->orderBy('customer_code')
            ->limit(20)
            ->get()
            ->map(fn($item) => $this->formatSearchItem($item, 'Borrower', $query));

        return response()->json($borrowers);
    }

    private function formatSearchItem(mixed $item, string $role, string $query = '')
    {
        [$matchedOn, $matchedValue] = $this->findSearchMatch($item, $query);

        return [
            'id' => $item->id,
            'name' => trim($item->first_name . ' ' . $item->last_name),
            'code' => $item->customer_code,
            'phone' => $item->phone,
            'village' => $item->village,
            'commune' => $item->commune,
            'district' => $item->district,
            'province' => $item->province,
            'role' => $role,
            'type' => strtolower(str_replace('-', '', $role)), // borrower, coborrower, guarantor
            'matched_on' => $matchedOn,
            'matched_value' => $matchedValue,
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function fullNameSql(string $firstColumn, string $secondColumn): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "COALESCE({$firstColumn}, '') || ' ' || COALESCE({$secondColumn}, '')";
        }

        return "CONCAT(COALESCE({$firstColumn}, ''), ' ', COALESCE({$secondColumn}, ''))";
    }

    private function findSearchMatch(mixed $item, string $query): array
    {
        $needle = mb_strtolower($query);
        $fields = [
            'Customer code' => (string) $item->customer_code,
            'Name' => trim(implode(' ', array_filter([
                $item->first_name,
                $item->last_name,
                $item->latin_name,
                $item->nickname,
            ]))),
        ];

        foreach ($fields as $label => $value) {
            if ($value !== '' && str_contains(mb_strtolower($value), $needle)) {
                return [$label, $value];
            }
        }

        $compactQuery = preg_replace('/[^\pL\pN]+/u', '', $query);
        if (mb_strlen($compactQuery) >= 4) {
            $phone = preg_replace('/[^\pL\pN]+/u', '', (string) $item->phone);
            if ($phone !== '' && str_contains(mb_strtolower($phone), mb_strtolower($compactQuery))) {
                return ['Phone', (string) $item->phone];
            }

            $idNumber = preg_replace('/[^\pL\pN]+/u', '', (string) $item->id_number);
            if ($idNumber !== '' && str_contains(mb_strtolower($idNumber), mb_strtolower($compactQuery))) {
                return ['ID number', '•••• ' . mb_substr($idNumber, -4)];
            }
        }

        return ['Customer', ''];
    }

    /**
     * Get detailed history for a specific customer.
     */
    public function getHistory(Request $request)
    {
        $id = $request->query('id');
        $type = $request->query('type'); // borrower, coborrower, guarantor

        if (!$id || !$type) {
            return response()->json(['error' => 'ID and Type are required'], 400);
        }

        $customer = null;
        $loans = [];

        switch ($type) {
            case 'borrower':
                $customer = Borrower::find($id);
                $loans = Loan::where('borrower_id', $id)
                    ->where('status', '!=', 'rejected')
                    ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer', 'product', 'paymentQr'])
                    ->get();
                break;
            case 'coborrower':
                $customer = CoBorrower::find($id);
                $loans = Loan::where('co_borrower_id', $id)
                    ->where('status', '!=', 'rejected')
                    ->with(['payments', 'collaterals', 'borrower', 'guarantor', 'officer', 'product', 'paymentQr'])
                    ->get();
                break;
            case 'guarantor':
                $customer = Guarantor::find($id);
                $loans = Loan::where('guarantor_id', $id)
                    ->where('status', '!=', 'rejected')
                    ->with(['payments', 'collaterals', 'borrower', 'coBorrower', 'officer', 'product', 'paymentQr'])
                    ->get();
                break;
        }

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $loansPayload = $loans->map(fn(Loan $loan) => $this->loanToHistoryArray($loan))->values()->all();

        return response()->json([
            'customer' => $customer,
            'loans' => $loansPayload
        ]);
    }

    /**
     * Get customer history by contract / loan code.
     */
    public function getHistoryByContract(Request $request)
    {
        $contractNo = $request->query('contract_no');
        if (!$contractNo || !is_string($contractNo)) {
            return response()->json(['error' => 'contract_no is required'], 400);
        }

        $contractNo = trim($contractNo);
        $loan = Loan::where('loan_code', $contractNo)
            ->where('status', '!=', 'rejected')
            ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer', 'borrower', 'product', 'paymentQr'])
            ->orderBy('id', 'desc')
            ->first();

        if (!$loan) {
            return response()->json(['error' => 'Contract not found'], 404);
        }

        $borrowerId = $loan->borrower_id;
        $customer = Borrower::find($borrowerId);
        if (!$customer) {
            return response()->json(['error' => 'Borrower not found'], 404);
        }

        $loans = Loan::where('borrower_id', $borrowerId)
            ->where('status', '!=', 'rejected')
            ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer', 'product', 'paymentQr'])
            ->get();

        $loansPayload = $loans->map(fn(Loan $l) => $this->loanToHistoryArray($l))->values()->all();

        return response()->json([
            'customer' => $customer,
            'loans' => $loansPayload,
        ]);
    }

    public function exportPaymentHistory(Request $request, int $id)
    {
        $loan = Loan::with('payments', 'borrower', 'coBorrower', 'guarantor')->findOrFail($id);
        
        $historyData = $this->loanToHistoryArray($loan);
        
        $customerName = trim(($loan->borrower->first_name ?? '') . ' ' . ($loan->borrower->last_name ?? ''));
        if (empty($customerName)) {
            $customerName = 'N/A';
        }

        $export = new \App\Exports\Excel\PaymentHistoryExcelExport();
        return $export->download($historyData, $loan->loan_code ?? 'N/A', $customerName, $loan->currency, $request);
    }
}
