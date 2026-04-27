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

        $dueToday = Loan::with(['borrower' => function($q) { $q->withTrashed(); }])
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
                $dueAmount = ($nextPayment->principal_amount + $nextPayment->interest_amount + ($nextPayment->fee_amount ?? 0)) - $nextPayment->total_paid;
                $symbol = (strpos($loan->currency, 'KHR') !== false) ? '៛' : '$';
                return [
                    'id' => (string) $loan->id,
                    'name' => $loan->borrower 
                        ? ($loan->borrower->last_name . ' ' . $loan->borrower->first_name)
                        : 'Unknown (Deleted)',
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
        $overdueLoans = Loan::with(['borrower' => function($q) { $q->withTrashed(); }])
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
                $dueAmount = ($payment->principal_amount + $payment->interest_amount + ($payment->fee_amount ?? 0)) - $payment->total_paid;
                $dpd = (int) $today->diffInDays(Carbon::parse($payment->payment_date));
                $overdueRows->push([
                    'id' => (string) $loan->id,
                    'name' => $loan->borrower 
                        ? ($loan->borrower->last_name . ' ' . $loan->borrower->first_name)
                        : 'Unknown (Deleted)',
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

        $loans = Loan::with(['borrower' => function($q) { $q->withTrashed(); }])
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
                'name' => $loan->borrower 
                    ? ($loan->borrower->last_name . ' ' . $loan->borrower->first_name)
                    : 'Unknown (Deleted)',
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
            // Acquire Pessimistic Lock (FOR UPDATE) to prevent Race Conditions (Double-Clicks)
            $loan = Loan::where('id', $validated['loan_id'])->lockForUpdate()->firstOrFail();

            $waivedAmount = $validated['waived_amount'] ?? 0;
            $penaltyAmountTotal = $validated['penalty_amount'] ?? 0;
            $feePaid = $validated['fee_amount'] ?? 0;

            // Validation: Prevent withdrawing more than current prepayment balance
            if ($validated['repayment_type'] === 'Withdraw') {
                $totalPrepaid = RepaymentTransaction::where('loan_id', $loan->id)
                    ->where('repayment_type', 'Prepayment')
                    ->sum('prepayment_paid');

                $totalWithdrawn = RepaymentTransaction::where('loan_id', $loan->id)
                    ->where('repayment_type', 'Withdraw')
                    ->sum('withdrawn_prepayment');

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

            if ($validated['repayment_type'] === 'Withdraw') {
                $totalToDistribute = 0; // Withdrawals do not pay off installments
                $cashPenaltyPaid = 0;   // Ignore any penalty input
                $penaltyAmountTotal = 0;
            } else {
                // Penalty is covered first by Cash/Waiver, then the rest of cash goes to Fee, Interest, Principal
                $totalToDistribute = $validated['amount_paid'] - $cashPenaltyPaid;
            }

            // Penalty is covered by both Cash and Waiver (Total settlement)
            $totalPenaltySettled = $penaltyAmountTotal;

            // Fetch unpaid installments
            $installments = Payment::where('loan_id', $loan->id)
                ->whereRaw('total_paid < (principal_amount + interest_amount + COALESCE(fee_amount, 0))')
                ->orderBy('payment_date', 'asc')
                ->get();

            if ($installments->isEmpty() && $validated['repayment_type'] !== 'Withdraw') {
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
                $dueForFirst = ($firstInst->principal_amount + $firstInst->interest_amount + $firstInst->fee_amount) - $firstInst->total_paid;
                // Allow small rounding difference.
                if (abs($totalToDistribute - $dueForFirst) > 0.01) {
                    throw new \Exception("Total payment (Paid Amount) must cover the current installment principal/interest/fee due ($dueForFirst) plus any penalty.");
                }
            }

            // Pay Off mode validation: must pay exactly the remaining balance
            if ($validated['repayment_type'] === 'Pay Off') {
                $totalPrincipalRemaining = 0;
                $dueInterest = 0;
                $dueFee = 0;

                foreach ($installments as $idx => $inst) {
                    $alreadyPaidToPrinInt = max(0, $inst->total_paid - ($inst->fee_paid ?? 0));
                    $interestPaidSoFar = min($inst->interest_amount, $alreadyPaidToPrinInt);
                    $principalPaidSoFar = max(0, $alreadyPaidToPrinInt - $interestPaidSoFar);

                    $totalPrincipalRemaining += ($inst->principal_amount - $principalPaidSoFar);

                    $dueFee += (($inst->fee_amount ?? 0) - ($inst->fee_paid ?? 0));

                    if ($idx === 0) {
                        $dueInterest += ($inst->interest_amount - $interestPaidSoFar);
                    } elseif ($idx === 1) {
                        $dueInterest += $inst->interest_amount; // 1 month advance interest penalty
                    }
                }

                $expectedPayOff = round($totalPrincipalRemaining + $dueInterest + $dueFee, 2);

                if (abs($totalToDistribute - $expectedPayOff) > 0.01) {
                    $expectedTotalWithPenalty = round($expectedPayOff + $cashPenaltyPaid, 2);
                    throw new \Exception("Total payment (Paid Amount) must perfectly match the Pay Off amount ($expectedTotalWithPenalty). (Principal: $totalPrincipalRemaining, Interest: $dueInterest, Fee: $dueFee, Penalty: $cashPenaltyPaid).");
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
                'paid_off_amount' => 0, // Calculated after distribution
                'recovery_amount' => $validated['repayment_type'] === 'Recovery' ? $validated['amount_paid'] : 0,
                'withdrawn_prepayment' => in_array($validated['repayment_type'], ['Withdraw', 'Refinance', 'Reschedule']) ? $validated['amount_paid'] : 0,
            ]);
            $totalPrincipalPaid = 0;
            $firstRowPrincipalPaid = 0;
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

                // Waterfall Priority for this installment: Fee -> Interest -> Principal
                // 1. Fee
                $dueFee = $inst->fee_amount - $inst->fee_paid;
                $feeApplied = round(min($totalToDistribute, $dueFee), 2);
                $totalToDistribute -= $feeApplied;
                $inst->fee_paid = round($inst->fee_paid + $feeApplied, 2);

                if ($totalToDistribute <= 0.001) {
                    $inst->total_paid = round($inst->total_paid + $feeApplied, 2);
                    $inst->save();
                    $lastUpdatedInst = $inst;
                    continue;
                }

                // 2. Interest
                $alreadyPaidToPrinInt = max(0, $inst->total_paid - $inst->fee_paid);
                $interestPaidSoFar = min($inst->interest_amount, $alreadyPaidToPrinInt);
                $dueInterest = $inst->interest_amount - $interestPaidSoFar;

                $interestToPay = 0;
                // Only pay interest if NOT Pay Off (for future rows) OR if it's 1st or 2nd unpaid row
                $isFirstOrSecond = ($inst === $installments->first() || ($installments->count() > 1 && $inst === $installments[1]));
                if ($validated['repayment_type'] !== 'Pay Off' || $isFirstOrSecond) {
                    $interestToPay = round(min($totalToDistribute, $dueInterest), 2);
                    $totalInterestPaid += $interestToPay;
                    $totalToDistribute -= $interestToPay;
                }

                // 3. Principal
                $principalPaidSoFar = max(0, $alreadyPaidToPrinInt - $interestPaidSoFar);
                $duePrincipal = $inst->principal_amount - $principalPaidSoFar;
                $principalToPay = 0;

                if ($totalToDistribute > 0.001) {
                    $principalToPay = round(min($totalToDistribute, $duePrincipal), 2);
                    $totalPrincipalPaid += $principalToPay;
                    $totalToDistribute -= $principalToPay;

                    if ($inst === $installments->first()) {
                        $firstRowPrincipalPaid = $principalToPay;
                    }
                }

                $appliedToThisRow = round($feeApplied + $interestToPay + $principalToPay, 2);
                $inst->total_paid = round($inst->total_paid + $appliedToThisRow, 2);
                $inst->repayment_transaction_id = $transaction->id;
                $inst->updated_at = $now;
                $inst->save();
                $lastUpdatedInst = $inst;

                // SPECIAL INDUSTRY STANDARD:
                // If this installment is NOW FULLY PAID, and we still have money left,
                // and it was a "Prepayment", we apply the remainder to Principal ONLY of the WHOLE loan
                // and then RE-CALCULATE THE WHOLE SCHEDULE.
                if ($totalToDistribute > 0.001 && $validated['repayment_type'] === 'Prepayment') {
                    // Extra money beyond the scheduled installment → prepayment surplus
                    // This reduces loan principal but is NOT counted as scheduled "principal_paid"
                    $extraPrincipal = round($totalToDistribute, 2);
                    $totalPrepaymentGenerated += $extraPrincipal;
                    $lastUpdatedInst->total_paid = round($lastUpdatedInst->total_paid + $totalToDistribute, 2);
                    $lastUpdatedInst->prepayment = $extraPrincipal; // ← save prepayment column
                    $lastUpdatedInst->save();
                    $totalToDistribute = 0;
                    break;
                }
            }

            // Update transaction details
            $transaction->update([
                'principal_paid' => $totalPrincipalPaid,
                'interest_paid' => $totalInterestPaid,
                'penalty_paid' => $totalPenaltyPaid,
                'prepayment_paid' => round($totalPrepaymentGenerated, 2),
            ]);

            if ($validated['repayment_type'] === 'Pay Off') {
                $actualPrincipalPaidOnFirstRow = $firstRowPrincipalPaid;
                $paidOffAmount = max(0, $totalPrincipalPaid - $actualPrincipalPaidOnFirstRow);

                $transaction->update([
                    'principal_paid' => round($actualPrincipalPaidOnFirstRow, 2),
                    'paid_off_amount' => round($paidOffAmount, 2),
                ]);
            }

            // Trigger Schedule Recalculation for Prepayment, Pay Off, Partial, or Withdraw
            if (in_array($validated['repayment_type'], ['Prepayment', 'Pay Off', 'Partial', 'Withdraw'])) {
                $loan->recalculateSchedule();
            }

            // Special handling for Pay Off: If all principal is settled, mark loan as completed
            if ($validated['repayment_type'] === 'Pay Off') {
                $unpaidPrincipalCount = Payment::where('loan_id', $loan->id)
                    ->whereRaw('total_paid < (COALESCE(principal_amount, 0) + COALESCE(fee_amount, 0) - 0.01)')
                    ->count();

                if ($unpaidPrincipalCount === 0) {
                    // All principal (and fees) is paid. Mark all future installments as "completed" 
                    // by setting total_paid = whatever is currently paid (which covers principal).
                    // We do NOT set it to principal + interest because we want to waive future interest.
                    Payment::where('loan_id', $loan->id)
                        ->whereRaw('total_paid < (principal_amount + interest_amount)')
                        ->each(function (Payment $p) use ($now) {
                            // Close the installment. Its total_paid remains whatever was actually collected.
                            $p->update([
                                'updated_at' => $now
                            ]);
                        });
                }
            }

            // Recalculate loan aging status in real-time
            $loan->updateAging();

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

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $transaction = RepaymentTransaction::findOrFail($id);
            $loan = Loan::findOrFail($transaction->loan_id);

            // Prevent voiding "Pay Off" transactions
            if ($transaction->repayment_type === 'Pay Off') {
                return response()->json([
                    'message' => 'Cannot void a Pay Off transaction.',
                ], 422);
            }

            if ($transaction->repayment_type === 'Withdraw') {
                // Withdrawing a prepayment doesn't affect installments, just delete the record
                $transaction->delete();
            } else {
                // Find all installments that were affected by this specific transaction
                $installments = Payment::where('loan_id', $loan->id)
                    ->where('repayment_transaction_id', $transaction->id)
                    ->get();

                // If no direct link (older data), fallback to reverse waterfall on latest paid
                if ($installments->isEmpty()) {
                    $installments = Payment::where('loan_id', $loan->id)
                        ->where('total_paid', '>', 0)
                        ->orderBy('id', 'desc')
                        ->get();
                }

                $feeToReverse = (float) $transaction->fee_paid;
                $interestToReverse = (float) $transaction->interest_paid;
                $principalToReverse = (float) ($transaction->principal_paid + $transaction->paid_off_amount);

                /** @var \App\Models\Payment $inst */
                foreach ($installments as $inst) {
                    // Reverse Fee
                    if ($feeToReverse > 0.001 && $inst->fee_paid > 0) {
                        $reduceFee = min($feeToReverse, (float) $inst->fee_paid);
                        $inst->fee_paid = round($inst->fee_paid - $reduceFee, 2);
                        $inst->total_paid = round($inst->total_paid - $reduceFee, 2);
                        $feeToReverse -= $reduceFee;
                    }

                    // Reverse Principal & Interest
                    $paidExcludingFee = max(0, (float) $inst->total_paid - (float) $inst->fee_paid);
                    $interestPaidOnRow = min((float) $inst->interest_amount, $paidExcludingFee);
                    $principalPaidOnRow = max(0, $paidExcludingFee - $interestPaidOnRow);

                    if ($principalToReverse > 0.001 && $principalPaidOnRow > 0) {
                        $reducePrin = min($principalToReverse, $principalPaidOnRow);
                        $inst->total_paid = round($inst->total_paid - $reducePrin, 2);
                        $principalToReverse -= $reducePrin;
                        $paidExcludingFee -= $reducePrin;
                    }

                    if ($interestToReverse > 0.001 && $interestPaidOnRow > 0) {
                        $reduceInt = min($interestToReverse, $interestPaidOnRow);
                        $inst->total_paid = round($inst->total_paid - $reduceInt, 2);
                        $interestToReverse -= $reduceInt;
                    }

                    // If row is now effectively unpaid, clear the transaction link
                    if ($inst->total_paid <= 0.001) {
                        $inst->repayment_transaction_id = null;
                        $inst->fee_paid = 0;
                        $inst->total_paid = 0;
                    }

                    $inst->save();

                    if ($feeToReverse <= 0.001 && $principalToReverse <= 0.001 && $interestToReverse <= 0.001) {
                        // Also null out the tag on any other rows that might have been part of this transaction
                        Payment::where('repayment_transaction_id', $transaction->id)
                            ->update(['repayment_transaction_id' => null]);
                        break;
                    }
                }

                $transaction->delete();
            }

            // Recalculate schedule (handles missing prepayment and resets future installments)
            $loan->recalculateSchedule();

            // Update aging and status
            $loan->updateAging();

            $unpaidCount = Payment::where('loan_id', $loan->id)
                ->whereRaw('total_paid < (COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0))')
                ->count();

            if ($unpaidCount > 0 && $loan->status === 'completed') {
                $loan->update(['status' => 'active']);
            }

            $loan->update(['total_paid' => $loan->payments()->sum('total_paid')]);

            return response()->json([
                'message' => 'Transaction voided successfully',
                'loan_status' => $loan->status
            ]);
        });
    }
}
