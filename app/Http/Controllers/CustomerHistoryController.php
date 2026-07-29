<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\CoBorrower;
use App\Models\Guarantor;
use App\Models\Loan;
use Illuminate\Http\Request;

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
        $daysOverdue = (int) $loan->aging;
        if ($daysOverdue < 0) $daysOverdue = 0;

        $dateOverdueStr = $earliestOverdue ? $earliestOverdue->format('Y-m-d') : '';

        // penalty rate
        if ($loan->penalty_rate !== null) {
            $penaltyPerDay = (float)$loan->penalty_rate;
        } else {
            $isKHR = str_contains(strtoupper($loan->currency ?? ''), 'KHR');
            $penaltyPerDay = $isKHR ? 10000.0 : 2.5;
        }
        $penaltyGross = $daysOverdue * $penaltyPerDay;

        // Add penalty paid so far including waivers (total lifetime)
        $penaltyPaidSoFar = (float) \App\Models\RepaymentTransaction::where('loan_id', $loan->id)
            ->sum(\Illuminate\Support\Facades\DB::raw('penalty_paid + waived_amount'));
        $arr['penalty_paid_so_far'] = $penaltyPaidSoFar;

        $penaltyDue = $penaltyGross - $penaltyPaidSoFar;
        if ($penaltyDue < 0) $penaltyDue = 0.0;

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

        if (!$isFullyPaid && $isOverdue && $dDate) {
            $diff = (int) $dDate->diffInDays($nDate, false);
            $scheduleOnTimeLabel = "-$diff";
        } elseif ($isFullyPaid && $payDate && $dDate) {
            $diff = (int) $dDate->diffInDays($payDate, false);
            $scheduleOnTimeLabel = $diff > 0 ? "-$diff" : ($diff < 0 ? (string)(abs($diff) + 1) : "0");
        }

        if ($p->total_paid > 0 && $payDate && $dDate) {
            $diff = (int) $dDate->diffInDays($payDate, false);
            $paymentOnTimeLabel = $diff > 0 ? "-$diff" : ($diff < 0 ? (string)(abs($diff) + 1) : "0");
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
                $allocRepaymentType = isset($allocationTxMap[$a->repayment_transaction_id]) ? $allocationTxMap[$a->repayment_transaction_id]->repayment_type : null;
                
                $allocOnTimeLabel = "0";
                if ($dDate) {
                    $allocDateStr = $a->transaction_date ?: $a->created_at;
                    $aDate = $allocDateStr ? \Carbon\Carbon::parse($allocDateStr)->startOfDay() : null;
                    if ($aDate) {
                        $diff = (int) $dDate->diffInDays($aDate, false);
                        $allocOnTimeLabel = $diff > 0 ? "-$diff" : ($diff < 0 ? (string)(abs($diff) + 1) : "0");
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
                    'transaction_date' => isset($allocationTxMap[$a->repayment_transaction_id]) ? $allocationTxMap[$a->repayment_transaction_id]->transaction_date : null,
                    'alloc_on_time_label' => $allocOnTimeLabel,
                    'alloc_total_installment' => (float)$a->principal_applied + (float)$a->interest_applied + (float)$a->fee_applied,
                ];
            })->all(),
        ];
    }
    /**
     * Search for customers across all roles.
     */
    public function search(Request $request)
    {
        $query = $request->query('query');
        if (!$query) {
            return response()->json([]);
        }

        $borrowers = Borrower::where(function ($q) use ($query) {
            $like = "%{$query}%";
            $queryNoSpace = str_replace(' ', '', $query);
            $likeNoSpace = "%{$queryNoSpace}%";

            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('latin_name', 'like', $like)
                    ->orWhere('nickname', 'like', $like)
                ->orWhere('id_number', 'like', $like)
                ->orWhere(\Illuminate\Support\Facades\DB::raw("REPLACE(id_number, ' ', '')"), 'like', $likeNoSpace)
                ->orWhere('phone', 'like', $like)
                ->orWhere(\Illuminate\Support\Facades\DB::raw("REPLACE(phone, ' ', '')"), 'like', $likeNoSpace)
                ->orWhere('customer_code', 'like', $like)
                ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(last_name, ' ', first_name)"), 'like', $like)
                ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', $like);
        })
            ->get()
            ->map(fn($item) => $this->formatSearchItem($item, 'Borrower'));

        return response()->json($borrowers);
    }

    private function formatSearchItem(mixed $item, string $role)
    {
        return [
            'id' => $item->id,
            'name' => $item->first_name . ' ' . $item->last_name,
            'code' => $item->customer_code,
            'phone' => $item->phone,
            'village' => $item->village,
            'commune' => $item->commune,
            'district' => $item->district,
            'province' => $item->province,
            'role' => $role,
            'type' => strtolower(str_replace('-', '', $role)) // borrower, coborrower, guarantor
        ];
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
                    ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer', 'product', 'paymentQr'])
                    ->get();
                break;
            case 'coborrower':
                $customer = CoBorrower::find($id);
                $loans = Loan::where('co_borrower_id', $id)
                    ->with(['payments', 'collaterals', 'borrower', 'guarantor', 'officer', 'product', 'paymentQr'])
                    ->get();
                break;
            case 'guarantor':
                $customer = Guarantor::find($id);
                $loans = Loan::where('guarantor_id', $id)
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
