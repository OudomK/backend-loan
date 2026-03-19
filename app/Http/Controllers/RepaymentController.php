<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RepaymentController extends Controller
{
    /**
     * Get loans due today or overdue.
     */
    public function getDueList()
    {
        $today = Carbon::today();

        $dueToday = Loan::with('borrower')
            ->where('status', 'active')
            ->whereHas('payments', function ($query) use ($today) {
                $query->where('payment_date', $today)
                    ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)');
            })
            ->get();

        // Due Today: one row per loan (installment due today)
        $formatDueToday = function ($loans) use ($today) {
            return $loans->map(function ($loan) use ($today) {
                $nextPayment = $loan->payments()
                    ->whereRaw('total_paid < (principal_amount + interest_amount)')
                    ->where('payment_date', $today->toDateString())
                    ->orderBy('payment_date', 'asc')
                    ->first();
                if (!$nextPayment) {
                    $nextPayment = $loan->payments()
                        ->whereRaw('total_paid < (principal_amount + interest_amount)')
                        ->orderBy('payment_date', 'asc')
                        ->first();
                }
                $dueAmount = ($nextPayment->principal_amount + $nextPayment->interest_amount) - $nextPayment->total_paid;
                $symbol = (strpos($loan->currency, 'KHR') !== false) ? '៛' : '$';
                return [
                    'id' => (string) $loan->id,
                    'name' => $loan->borrower->last_name . ' ' . $loan->borrower->first_name,
                    'code' => $loan->loan_code ?? ('L-' . str_pad($loan->id, 5, '0', STR_PAD_LEFT)),
                    'payment_date' => Carbon::parse($nextPayment->payment_date)->format('Y-m-d'),
                    'amount' => $symbol . number_format($dueAmount, 2),
                    'principal' => (string) number_format($nextPayment->principal_amount, 2),
                    'interest' => (string) number_format($nextPayment->interest_amount, 2),
                    'installment_no' => (string) $nextPayment->payment_number,
                    'dpd' => '0',
                    'symbol' => $symbol,
                ];
            });
        };

        // Overdue: one row per overdue installment (so "3 late" = 3 rows)
        $overdueRows = collect();
        $overdueLoans = Loan::with('borrower')
            ->where('status', 'active')
            ->whereHas('payments', function ($query) use ($today) {
                $query->where('payment_date', '<', $today)
                    ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)');
            })
            ->get();

        /** @var \App\Models\Loan $loan */
        foreach ($overdueLoans as $loan) {
            $overduePayments = $loan->payments()
                ->where('payment_date', '<', $today->toDateString())
                ->whereRaw('total_paid < (principal_amount + interest_amount)')
                ->orderBy('payment_date', 'asc')
                ->get();

            $symbol = (strpos($loan->currency, 'KHR') !== false) ? '៛' : '$';
            foreach ($overduePayments as $payment) {
                $dueAmount = ($payment->principal_amount + $payment->interest_amount) - $payment->total_paid;
                $dpd = (int) $today->diffInDays(Carbon::parse($payment->payment_date));
                $overdueRows->push([
                    'id' => (string) $loan->id,
                    'name' => $loan->borrower->last_name . ' ' . $loan->borrower->first_name,
                    'code' => $loan->loan_code ?? ('L-' . str_pad($loan->id, 5, '0', STR_PAD_LEFT)),
                    'payment_date' => Carbon::parse($payment->payment_date)->format('Y-m-d'),
                    'amount' => $symbol . number_format($dueAmount, 2),
                    'principal' => (string) number_format($payment->principal_amount, 2),
                    'interest' => (string) number_format($payment->interest_amount, 2),
                    'installment_no' => (string) $payment->payment_number,
                    'dpd' => (string) $dpd,
                    'symbol' => $symbol,
                ]);
            }
        }

        return response()->json([
            'due_today' => $formatDueToday($dueToday),
            'overdue' => $overdueRows->values()->all(),
        ]);
    }

    /**
     * Search for active loans.
     */
    public function search(Request $request)
    {
        $query = $request->input('query');

        $loans = Loan::with('borrower')
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('loan_code', 'LIKE', "%$query%")
                    ->orWhereHas('borrower', function ($bq) use ($query) {
                        $bq->where('first_name', 'LIKE', "%$query%")
                            ->orWhere('last_name', 'LIKE', "%$query%");
                    });
            })
            ->limit(10)
            ->get();

        return response()->json($loans->map(function ($loan) {
            return [
                'id' => (string) $loan->id,
                'name' => $loan->borrower->last_name . ' ' . $loan->borrower->first_name,
                'code' => $loan->loan_code ?? ('L-' . str_pad($loan->id, 5, '0', STR_PAD_LEFT)),
                'principal' => (string) $loan->amount,
                'interest' => (string) $loan->interest_rate, // Simple mapping for search
            ];
        }));
    }

    /**
     * Get unpaid installments for a specific loan and fee status (for one-time fee display).
     */
    public function getInstallments($loan_id)
    {
        $loan = Loan::find($loan_id);
        $installments = Payment::where('loan_id', $loan_id)
            ->whereRaw('total_paid < (principal_amount + interest_amount)')
            ->orderBy('payment_date', 'asc')
            ->get();

        $feeType = $loan ? (trim((string) ($loan->admin_fee_type ?? '')) ?: 'one_time') : 'one_time';
        $totalFee = $loan ? ($loan->amount * ((float) ($loan->admin_fee ?? 0) / 100)) : 0;
        $feePaidSoFar = (float) RepaymentTransaction::where('loan_id', $loan_id)->sum('fee_paid');

        return response()->json([
            'installments' => $installments,
            'fee_type' => $feeType,
            'total_fee' => round($totalFee, 2),
            'fee_paid_so_far' => round($feePaidSoFar, 2),
        ]);
    }

    /**
     * Process a repayment transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'collector_id' => 'required|exists:loan_officers,id',
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'repayment_type' => 'required|string|in:Normal,Prepayment,Partial,Pay Off,Refinance,Reschedule,Recovery,Withdraw',
            'transaction_date' => 'required|date',
            'penalty_amount' => 'nullable|numeric|min:0',
            'fee_amount' => 'nullable|numeric|min:0',
            'waived_amount' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $loan = Loan::findOrFail($validated['loan_id']);
            $waivedAmount = $validated['waived_amount'] ?? 0;
            $penaltyAmountTotal = $validated['penalty_amount'] ?? 0;
            $feePaid = $validated['fee_amount'] ?? 0;

            // Validation: Prevent withdrawing more than current prepayment balance
            if ($validated['repayment_type'] === 'Withdraw') {
                $totalPrepaid = RepaymentTransaction::where('loan_id', $loan->id)
                    ->where('repayment_type', 'Prepayment')
                    ->sum('amount_paid');

                $totalWithdrawn = RepaymentTransaction::where('loan_id', $loan->id)
                    ->where('repayment_type', 'Withdraw')
                    ->sum('amount_paid');

                $balance = round($totalPrepaid - $totalWithdrawn, 2);

                if ($validated['amount_paid'] > $balance + 0.001) {
                    return response()->json([
                        'message' => "Withdrawal amount ({$validated['amount_paid']}) exceeds prepayment balance (" . number_format($balance, 2) . ")"
                    ], 422);
                }
            }

            // Per user rule: Waiver cannot exceed Penalty Due
            if ($waivedAmount > $penaltyAmountTotal) {
                $waivedAmount = $penaltyAmountTotal;
            }

            // Calculate how much penalty is paid in cash vs waived
            $cashPenaltyPaid = max(0, $penaltyAmountTotal - $waivedAmount);

            // The total amount to settle for Principal and Interest (CASH ONLY)
            $totalToDistribute = $validated['amount_paid'] - $cashPenaltyPaid - $feePaid;

            // Penalty is covered by both Cash and Waiver (Total settlement)
            $totalPenaltySettled = $penaltyAmountTotal;

            // Fetch unpaid installments
            $installments = Payment::where('loan_id', $loan->id)
                ->whereRaw('total_paid < (principal_amount + interest_amount)')
                ->orderBy('payment_date', 'asc')
                ->get();

            if ($installments->isEmpty()) {
                throw new \Exception("No unpaid installments found for this loan.");
            }

            // Keep installment-level penalty in sync so customer history can display it.
            if ($totalPenaltySettled > 0) {
                $firstInstallment = $installments->first();
                $firstInstallment->penalty_amount = round(($firstInstallment->penalty_amount ?? 0) + $totalPenaltySettled, 2);
                $firstInstallment->save();
            }

            // Normal mode validation: must pay exactly the current installment's due (excluding penalty)
            if ($validated['repayment_type'] === 'Normal') {
                $firstInst = $installments->first();
                $dueForFirst = ($firstInst->principal_amount + $firstInst->interest_amount) - $firstInst->total_paid;
                // Allow small rounding difference.
                if (abs($totalToDistribute - $dueForFirst) > 0.01) {
                    throw new \Exception("Total payment (Paid Amount) must cover the current installment principal/interest due ($dueForFirst) plus any penalty/fees.");
                }
            }

            // Record the transaction
            $transaction = RepaymentTransaction::create([
                'loan_id' => $loan->id,
                'collector_id' => $validated['collector_id'],
                'amount_paid' => $validated['amount_paid'],
                'waived_amount' => $waivedAmount,
                'principal_paid' => 0, // Will update after distribution
                'interest_paid' => 0,
                'penalty_paid' => $cashPenaltyPaid,
                'fee_paid' => $feePaid,
                'payment_method' => $validated['payment_method'],
                'repayment_type' => $validated['repayment_type'],
                'transaction_date' => $validated['transaction_date'],
                'paid_off_amount' => $validated['repayment_type'] === 'Pay Off' ? $validated['amount_paid'] : 0,
                'recovery_amount' => $validated['repayment_type'] === 'Recovery' ? $validated['amount_paid'] : 0,
                'withdrawn_prepayment' => in_array($validated['repayment_type'], ['Withdraw', 'Refinance', 'Reschedule']) ? $validated['amount_paid'] : 0,
            ]);
            $totalPrincipalPaid = 0;
            $totalInterestPaid = 0;
            $totalPrepaymentGenerated = 0; // Total surplus or future applications
            $totalPenaltyPaid = $cashPenaltyPaid;
            $lastUpdatedInst = null;
            $now = Carbon::now();
            $todayStr = Carbon::today()->toDateString();

            /** @var \App\Models\Payment $inst */
            foreach ($installments as $inst) {
                if ($totalToDistribute <= 0.001)
                    break;
        
                // Interest first, then principal (standard allocation)
                $dueInterest = $inst->interest_amount - min($inst->interest_amount, $inst->total_paid);
                $interestToPay = round(min($totalToDistribute, $dueInterest), 2);
                $totalInterestPaid += $interestToPay;
                $totalToDistribute -= $interestToPay;
        
                $principalToPay = 0;
                if ($totalToDistribute > 0.001) {
                    $principalPaidSoFar = max(0, $inst->total_paid - $inst->interest_amount);
                    $duePrincipal = $inst->principal_amount - $principalPaidSoFar;
                    $principalToPay = round(min($totalToDistribute, $duePrincipal), 2);
                    $totalPrincipalPaid += $principalToPay;
                    $totalToDistribute -= $principalToPay;
                }
        
                $appliedToThisRow = round($interestToPay + $principalToPay, 2);
                if (
                    $appliedToThisRow > 0 && 
                    $inst->payment_date > $todayStr && 
                    !in_array($validated['repayment_type'], ['Withdraw', 'Refinance', 'Reschedule', 'Recovery', 'Pay Off'])
                ) {
                    $totalPrepaymentGenerated += $appliedToThisRow;
                }

                $inst->total_paid = round($inst->total_paid + $appliedToThisRow, 2);
                $inst->prepayment = round(max(0, $totalToDistribute), 2);
                $inst->updated_at = $now;
                $inst->save();
                $lastUpdatedInst = $inst;
            }

            // Unallocated surplus is also a prepayment
            if ($totalToDistribute > 0.001) {
                $totalPrepaymentGenerated += $totalToDistribute;
            }

            // Avoid losing cents from rounding: apply any tiny remainder to last touched installment
            if ($lastUpdatedInst && $totalToDistribute > 0.001 && $totalToDistribute < 1) {
                $cap = ($lastUpdatedInst->principal_amount + $lastUpdatedInst->interest_amount) - $lastUpdatedInst->total_paid;
                $add = round(min($totalToDistribute, $cap), 2);
                if ($add > 0) {
                    $lastUpdatedInst->total_paid = round($lastUpdatedInst->total_paid + $add, 2);
                    $lastUpdatedInst->updated_at = $now;
                    $lastUpdatedInst->save();
                    $totalPrincipalPaid += $add;
                    $totalToDistribute -= $add;
                }
            }

            // Update transaction details
            $transaction->update([
                'principal_paid' => $totalPrincipalPaid,
                'interest_paid' => $totalInterestPaid,
                'penalty_paid' => $totalPenaltyPaid,
                'prepayment_paid' => round($totalPrepaymentGenerated, 2),
            ]);

            // Special handling for Pay Off: If all principal is settled, mark loan as completed
            if ($validated['repayment_type'] === 'Pay Off') {
                $unpaidPrincipalCount = Payment::where('loan_id', $loan->id)
                    ->whereRaw('total_paid < COALESCE(principal_amount, 0)')
                    ->count();

                if ($unpaidPrincipalCount === 0) {
                    // All principal is paid. Force all installments to 'fully paid' state by setting total_paid = principal+interest
                    // This effectively waives any remaining interest.
                    Payment::where('loan_id', $loan->id)->each(function (Payment $p) use ($now) {
                        $p->update([
                            'total_paid' => $p->principal_amount + $p->interest_amount,
                            'updated_at' => $now
                        ]);
                    });
                }
            }

            // Check if loan is completed
            $unpaidCount = Payment::where('loan_id', $loan->id)
                ->whereRaw('total_paid < (COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0))')
                ->count();

            if ($unpaidCount === 0) {
                $loan->update(['status' => 'completed']);
            }

            // Keep loans.total_paid in sync with sum of payments
            $loan->update(['total_paid' => $loan->payments()->sum('total_paid')]);

            return response()->json([
                'message' => 'Repayment processed successfully',
                'transaction' => $transaction,
                'loan_status' => $loan->status
            ]);
        });
    }
}
