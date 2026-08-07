<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RepaymentService
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{transaction: RepaymentTransaction, loan: Loan}
     */
    public function process(array $validated): array
    {
        return DB::transaction(function () use ($validated): array {
            // Acquire pessimistic lock to prevent double-processing the same loan.
            $loan = Loan::whereKey($validated['loan_id'])->lockForUpdate()->firstOrFail();
            $feeType = trim((string) ($loan->admin_fee_type ?? '')) ?: 'one_time';
            $loan->updateAging();
            $loan->refresh();
            $usesInstallmentFee = $feeType === 'monthly';

            $principalInterestAmount = (float) ($validated['amount_paid'] ?? 0);
            $waivedAmount = (float) ($validated['waived_amount'] ?? 0);
            $feePaid = $usesInstallmentFee ? (float) ($validated['fee_amount'] ?? 0) : 0.0;
            $penaltyAmountToPay = (float) ($validated['penalty_amount'] ?? 0);
            // The backend owns the amount due. A client may display its own preview,
            // but must never be allowed to change the loan's penalty balance.
            $penaltyDue = $loan->currentPenaltyDue(
                Carbon::parse($validated['transaction_date'])->startOfDay()
            );

            if (($penaltyAmountToPay + $waivedAmount) > $penaltyDue + 0.001) {
                throw new \RuntimeException('Penalty pay and waiver cannot be greater than the penalty due.');
            }

            $cashPenaltyPaid = round(max($penaltyAmountToPay, 0), 2);


            if (
                $validated['repayment_type'] !== 'Withdraw'
                && $principalInterestAmount <= 0
                && $cashPenaltyPaid <= 0
                && $waivedAmount <= 0
            ) {
                throw new \RuntimeException('Please enter a principal/interest amount, penalty payment, or waiver.');
            }

            if ($validated['repayment_type'] === 'Withdraw') {
                $totalPrepaid = (float) RepaymentTransaction::where('loan_id', $loan->id)
                    ->where('repayment_type', 'Prepayment')
                    ->sum('prepayment_paid');

                $totalWithdrawn = (float) RepaymentTransaction::where('loan_id', $loan->id)
                    ->where('repayment_type', 'Withdraw')
                    ->sum('withdrawn_prepayment');

                $balance = round($totalPrepaid - $totalWithdrawn, 2);

                if ((float) $validated['amount_paid'] > $balance + 0.001) {
                    throw new \RuntimeException(
                        "Withdrawal amount ({$validated['amount_paid']}) exceeds prepayment balance (" . number_format($balance, 2) . ')'
                    );
                }
            }

            if ($validated['repayment_type'] === 'Withdraw') {
                $totalToDistribute = 0.0;
            } else {
                $totalToDistribute = round($principalInterestAmount + $feePaid, 2);
            }

            $installmentDueExpression = $usesInstallmentFee
                ? 'total_paid < (principal_amount + interest_amount + COALESCE(fee_amount, 0))'
                : 'total_paid < (principal_amount + interest_amount)';
            $completedLoanExpression = $usesInstallmentFee
                ? 'total_paid < (COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0) + COALESCE(fee_amount, 0))'
                : 'total_paid < (COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0))';

            /** @var \Illuminate\Database\Eloquent\Collection<int, Payment> $installments */
            $installments = Payment::where('loan_id', $loan->id)
                ->whereRaw($installmentDueExpression)
                ->orderBy('payment_date', 'asc')
                ->get();

            // Use the same overdue definition when deciding whether to freeze this late period.
            $arrearExpr = $usesInstallmentFee
                ? 'total_paid < (principal_amount + interest_amount + COALESCE(fee_amount, 0) - 0.01)'
                : 'total_paid < (principal_amount + interest_amount - 0.01)';
            if ($installments->isEmpty() && $validated['repayment_type'] !== 'Withdraw') {
                throw new \RuntimeException('No unpaid installments found for this loan.');
            }

            if ($validated['repayment_type'] === 'Normal') {
                $firstInst = $installments->first();
                $dueForFirstPI = ($firstInst->principal_amount + $firstInst->interest_amount + ($usesInstallmentFee ? $firstInst->fee_amount : 0)) - $firstInst->total_paid;

                if (abs($totalToDistribute - $dueForFirstPI) > 0.01) {
                    throw new \RuntimeException(
                        'Total payment must cover the current installment due (' . number_format($dueForFirstPI, 2) . ').'
                    );
                }
            }

            $currentInstIndex = $installments->count() - 1;
            $chargeUpToIndex = $currentInstIndex;
            $today = Carbon::parse($validated['transaction_date'])->startOfDay();

            foreach ($installments as $idx => $instObj) {
                if (Carbon::parse($instObj->payment_date)->startOfDay()->gte($today)) {
                    $currentInstIndex = $idx;
                    $chargeUpToIndex = $idx + 1;
                    break;
                }
            }

            if ($validated['repayment_type'] === 'Pay Off') {
                $totalPrincipalRemaining = 0.0;
                $dueInterest = 0.0;
                $dueFee = 0.0;

                foreach ($installments as $idx => $inst) {
                    $alreadyPaidToPrinInt = max(0, (float) $inst->total_paid - (float) ($inst->fee_paid ?? 0));
                    $interestPaidSoFar = min((float) $inst->interest_amount, $alreadyPaidToPrinInt);
                    $principalPaidSoFar = max(0, $alreadyPaidToPrinInt - $interestPaidSoFar);

                    $totalPrincipalRemaining += ((float) $inst->principal_amount - $principalPaidSoFar);
                    if ($usesInstallmentFee) {
                        $dueFee += ((float) ($inst->fee_amount ?? 0) - (float) ($inst->fee_paid ?? 0));
                    }

                    if ($idx <= $chargeUpToIndex) {
                        $dueInterest += ((float) $inst->interest_amount - $interestPaidSoFar);
                    }
                }

                $expectedPayOffTotal = round($totalPrincipalRemaining + $dueInterest + $dueFee, 2);

                if (abs($totalToDistribute - $expectedPayOffTotal) > 0.01) {
                    throw new \RuntimeException(
                        'Total payment must match the Pay Off amount (' . number_format($expectedPayOffTotal, 2) . ').'
                    );
                }
            }

            $transaction = RepaymentTransaction::create([
                'loan_id' => $loan->id,
                'collector_id' => $validated['collector_id'],
                'amount_paid' => $principalInterestAmount,
                'waived_amount' => $waivedAmount,
                'principal_paid' => 0,
                'interest_paid' => 0,
                'penalty_paid' => $cashPenaltyPaid,
                'fee_paid' => $feePaid,
                'payment_method' => $validated['payment_method'],
                'repayment_type' => $validated['repayment_type'],
                'transaction_date' => $validated['transaction_date'],
                'paid_off_amount' => 0,
                'recovery_amount' => $validated['repayment_type'] === 'Recovery' ? $validated['amount_paid'] : 0,
                'withdrawn_prepayment' => in_array($validated['repayment_type'], ['Withdraw', 'Refinance', 'Reschedule'], true)
                    ? $validated['amount_paid']
                    : 0,
            ]);

            $totalPrincipalPaid = 0.0;
            $firstRowPrincipalPaid = 0.0;
            $totalInterestPaid = 0.0;
            $totalPrepaymentGenerated = 0.0;
            $totalPenaltyPaid = $cashPenaltyPaid;
            $lastUpdatedInst = null;
            $now = Carbon::now();

            /** @var Payment|null $firstInst */
            $firstInst = $installments->first();
            /** @var Payment|null $secondInst */
            $secondInst = $installments->get(1);

            /** @var Payment $inst */
            // Save first installment's due amount before the loop modifies it
            $firstInstOriginalDue = 0.0;
            if ($firstInst) {
                $firstInstOriginalDue = round(
                    (float) $firstInst->principal_amount
                    + (float) $firstInst->interest_amount
                    + ($usesInstallmentFee ? (float) $firstInst->fee_amount : 0)
                    - (float) $firstInst->total_paid,
                    2
                );
            }
            foreach ($installments as $idx => $inst) {
                if ($totalToDistribute <= 0.001) {
                    break;
                }

                if ($validated['repayment_type'] === 'Prepayment' && $idx > $currentInstIndex) {
                    break;
                }

                $existingFeePaid = (float) $inst->fee_paid;
                $existingTotalPaid = (float) $inst->total_paid;
                $dueFee = (float) $inst->fee_amount - $existingFeePaid;
                $feeApplied = round(min($totalToDistribute, $dueFee), 2);
                $totalToDistribute -= $feeApplied;
                $inst->fee_paid = round($existingFeePaid + $feeApplied, 2);

                if ($totalToDistribute <= 0.001) {
                    $inst->total_paid = round($existingTotalPaid + $feeApplied, 2);
                    $inst->save();
                    $lastUpdatedInst = $inst;

                    if ($feeApplied > 0) {
                        \App\Models\PaymentAllocation::create([
                            'payment_id' => $inst->id,
                            'repayment_transaction_id' => $transaction->id,
                            'amount_applied' => $feeApplied,
                            'fee_applied' => $feeApplied,
                            'interest_applied' => 0,
                            'principal_applied' => 0,
                        ]);
                    }

                    continue;
                }

                $alreadyPaidToPrinInt = max(0, $existingTotalPaid - $existingFeePaid);
                $interestPaidSoFar = min((float) $inst->interest_amount, $alreadyPaidToPrinInt);
                $dueInterest = (float) $inst->interest_amount - $interestPaidSoFar;

                $interestToPay = 0.0;
                $isWithinPayOffInterest = false;
                if ($validated['repayment_type'] === 'Pay Off') {
                    $isWithinPayOffInterest = $idx <= $chargeUpToIndex;
                }

                if ($validated['repayment_type'] !== 'Pay Off' || $isWithinPayOffInterest) {
                    $interestToPay = round(min($totalToDistribute, $dueInterest), 2);
                    $totalInterestPaid += $interestToPay;
                    $totalToDistribute -= $interestToPay;
                }

                $principalPaidSoFar = max(0, $alreadyPaidToPrinInt - $interestPaidSoFar);
                $duePrincipal = (float) $inst->principal_amount - $principalPaidSoFar;
                $principalToPay = 0.0;

                if ($totalToDistribute > 0.001) {
                    $principalToPay = round(min($totalToDistribute, $duePrincipal), 2);
                    $totalPrincipalPaid += $principalToPay;
                    $totalToDistribute -= $principalToPay;

                    if ($inst->is($firstInst)) {
                        $firstRowPrincipalPaid = $principalToPay;
                    }
                }

                $appliedToThisRow = round($feeApplied + $interestToPay + $principalToPay, 2);
                $inst->total_paid = round($existingTotalPaid + $appliedToThisRow, 2);
                $inst->repayment_transaction_id = $transaction->id;
                $inst->updated_at = $now;
                $inst->save();
                $lastUpdatedInst = $inst;

                if ($appliedToThisRow > 0) {
                    \App\Models\PaymentAllocation::create([
                        'payment_id' => $inst->id,
                        'repayment_transaction_id' => $transaction->id,
                        'amount_applied' => $appliedToThisRow,
                        'fee_applied' => $feeApplied,
                        'interest_applied' => $interestToPay,
                        'principal_applied' => $principalToPay,
                    ]);
                }

                // Forward Apply: let the loop continue to pay next installments.
                // Any remaining balance after all installments will be stored as prepayment below.
            }

            // If there's still money left after all installments, store as prepayment
            if ($totalToDistribute > 0.001 && $lastUpdatedInst && $validated['repayment_type'] === 'Prepayment') {
                $extraPrincipal = round($totalToDistribute, 2);
                $totalPrepaymentGenerated += $extraPrincipal;
                $lastUpdatedInst->total_paid = round((float) $lastUpdatedInst->total_paid + $totalToDistribute, 2);
                $lastUpdatedInst->prepayment = round((float) ($lastUpdatedInst->prepayment ?? 0) + $extraPrincipal, 2);
                $lastUpdatedInst->save();

                \App\Models\PaymentAllocation::create([
                    'payment_id' => $lastUpdatedInst->id,
                    'repayment_transaction_id' => $transaction->id,
                    'amount_applied' => $extraPrincipal,
                    'fee_applied' => 0,
                    'interest_applied' => 0,
                    'principal_applied' => $extraPrincipal,
                ]);
            }

            // Track how much was paid beyond the CURRENT due installment (= advance payment)
            // (Removed flawed $advancePaid tracking logic that caused double counting of prepayment)

            // Allocate Penalty
            if ($cashPenaltyPaid > 0) {
                // Find the oldest unpaid installment
                $inst = Payment::where('loan_id', $loan->id)
                               ->whereRaw('total_paid < (principal_amount + interest_amount + COALESCE(fee_amount, 0) - 0.01)')
                               ->orderBy('payment_date', 'asc')
                               ->first();
                if ($inst) {
                    $inst->penalty_amount += $cashPenaltyPaid;
                    $inst->save();

                    \App\Models\PaymentAllocation::create([
                        'payment_id' => $inst->id,
                        'repayment_transaction_id' => $transaction->id,
                        'amount_applied' => $cashPenaltyPaid,
                        'fee_applied' => 0,
                        'interest_applied' => 0,
                        'principal_applied' => 0,
                        'penalty_applied' => $cashPenaltyPaid,
                    ]);
                }
            }

            $transaction->update([
                'principal_paid' => $totalPrincipalPaid,
                'interest_paid' => $totalInterestPaid,
                'penalty_paid' => $totalPenaltyPaid,
                'prepayment_paid' => round($totalPrepaymentGenerated, 2),
            ]);

            if ($validated['repayment_type'] === 'Pay Off') {
                $paidOffAmount = max(0, $totalPrincipalPaid - $firstRowPrincipalPaid);

                $transaction->update([
                    'principal_paid' => round($firstRowPrincipalPaid, 2),
                    'paid_off_amount' => round($paidOffAmount, 2),
                ]);
            }

            if (in_array($validated['repayment_type'], ['Prepayment', 'Pay Off', 'Partial', 'Withdraw'], true)) {
                $loan->recalculateSchedule();
            }

            if ($validated['repayment_type'] === 'Pay Off') {
                $unpaidPrincipalCount = Payment::where('loan_id', $loan->id)
                    ->whereRaw('total_paid < (COALESCE(principal_amount, 0) + COALESCE(fee_amount, 0) - 0.01)')
                    ->count();

                if ($unpaidPrincipalCount === 0) {
                    Payment::where('loan_id', $loan->id)
                        ->whereRaw('total_paid < (principal_amount + interest_amount)')
                        ->each(function (Payment $payment) use ($now): void {
                            $payment->update([
                                'updated_at' => $now,
                            ]);
                        });
                }
            }

            $loan->updateAging();

            $hasOverdueRowsAfterPayment = Payment::where('loan_id', $loan->id)
                ->where('payment_date', '<', Carbon::today()->toDateString())
                ->whereRaw($arrearExpr)
                ->exists();

            if (! $hasOverdueRowsAfterPayment) {
                // The late period has ended. Freeze the remaining penalty alongside
                // its locked aging, so neither grows until a later installment is late.
                $loan->accumulated_penalty = round(max(0, $penaltyDue - $cashPenaltyPaid - $waivedAmount), 2);

                if ($loan->accumulated_penalty <= 0.001) {
                    $loan->locked_aging = 0;
                    $loan->aging = 0;
                }

                $loan->save();
            }

            $unpaidCount = Payment::where('loan_id', $loan->id)
                ->whereRaw($completedLoanExpression)
                ->count();

            if ($unpaidCount === 0) {
                $loan->update(['status' => 'completed']);
            }

            $loan->update(['total_paid' => $loan->payments()->sum('total_paid')]);

            // Automatically record Penalty and Fees as General Revenue
            if ($totalPenaltyPaid > 0.001) {
                $penaltyCategory = \App\Models\RevenueCategory::where('slug', 'penalty_income')->first()
                    ?? \App\Models\RevenueCategory::where('name', 'LIKE', '%Penalty%')->first();
                if ($penaltyCategory) {
                    Revenue::create([
                        'revenue_category_id' => $penaltyCategory->id,
                        'loan_id' => $loan->id,
                        'repayment_transaction_id' => $transaction->id,
                        'amount' => $totalPenaltyPaid,
                        'currency' => $loan->currency,
                        'transaction_date' => $validated['transaction_date'],
                        'payment_method' => $validated['payment_method'],
                        'description' => "Penalty for loan {$loan->loan_code}",
                        'status' => 'completed',
                    ]);
                }
            }

            if ($feePaid > 0.001) {
                $serviceFeeCategory = \App\Models\RevenueCategory::where('slug', 'service_fees')->first()
                    ?? \App\Models\RevenueCategory::where('name', 'LIKE', '%Service%Fee%')->first();
                if ($serviceFeeCategory) {
                    Revenue::create([
                        'revenue_category_id' => $serviceFeeCategory->id,
                        'loan_id' => $loan->id,
                        'repayment_transaction_id' => $transaction->id,
                        'amount' => $feePaid,
                        'currency' => $loan->currency,
                        'transaction_date' => $validated['transaction_date'],
                        'payment_method' => $validated['payment_method'],
                        'description' => "Service fee for loan {$loan->loan_code}",
                        'status' => 'completed',
                    ]);
                }
            }

            return [
                'transaction' => $transaction->fresh(),
                'loan' => $loan->fresh(),
            ];
        });
    }

    /**
     * @param  RepaymentTransaction|int  $transaction
     * @return array{loan: Loan, transaction: RepaymentTransaction}
     */
    public function void(RepaymentTransaction|int $transaction): array
    {
        return DB::transaction(function () use ($transaction): array {
            $transaction = $transaction instanceof RepaymentTransaction
                ? $transaction
                : RepaymentTransaction::findOrFail($transaction);

            $transaction = RepaymentTransaction::whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();
            $loan = Loan::whereKey($transaction->loan_id)->lockForUpdate()->firstOrFail();

            if ($transaction->repayment_type === 'Pay Off') {
                throw new \RuntimeException('Cannot void a Pay Off transaction.');
            }

            if ($transaction->repayment_type === 'Withdraw') {
                $transaction->delete();
            } else {
                $allocations = \App\Models\PaymentAllocation::where('repayment_transaction_id', $transaction->id)->get();
                if ($allocations->isNotEmpty()) {
                    foreach ($allocations as $allocation) {
                        $inst = Payment::find($allocation->payment_id);
                        if ($inst) {
                            $actualAmountToSubtractFromTotalPaid = (float) $allocation->amount_applied - (float) ($allocation->penalty_applied ?? 0);
                            $inst->total_paid = round(max(0, (float) $inst->total_paid - $actualAmountToSubtractFromTotalPaid), 2);
                            $inst->fee_paid = round(max(0, (float) $inst->fee_paid - (float) $allocation->fee_applied), 2);
                            $inst->penalty_amount = round(max(0, (float) $inst->penalty_amount - (float) ($allocation->penalty_applied ?? 0)), 2);
                            if ($inst->total_paid <= 0.001 && $inst->penalty_amount <= 0.001) {
                                $inst->repayment_transaction_id = null;
                                $inst->fee_paid = 0;
                                $inst->total_paid = 0;
                                $inst->penalty_amount = 0;
                            } else {
                                // If this row's repayment_transaction_id was this transaction, but it still has total_paid > 0, 
                                // we should ideally set it to the PREVIOUS transaction ID. But without a full history of transaction IDs, 
                                // we can just set it to null or leave it. Leaving it is what the greedy fallback does too.
                                if ($inst->repayment_transaction_id === $transaction->id) {
                                    $inst->repayment_transaction_id = null;
                                }
                            }
                            $inst->save();
                        }
                    }
                    \App\Models\PaymentAllocation::where('repayment_transaction_id', $transaction->id)->delete();
                } else {
                    $installments = Payment::where('loan_id', $loan->id)
                        ->where('repayment_transaction_id', $transaction->id)
                        ->get();

                    if ($installments->isEmpty()) {
                        $installments = Payment::where('loan_id', $loan->id)
                            ->where('total_paid', '>', 0)
                            ->orderBy('id', 'desc')
                            ->get();
                    }

                    $feeToReverse = (float) $transaction->fee_paid;
                    $interestToReverse = (float) $transaction->interest_paid;
                    $principalToReverse = (float) ($transaction->principal_paid + $transaction->paid_off_amount);

                    /** @var Payment $inst */
                    foreach ($installments as $inst) {
                        if ($feeToReverse > 0.001 && $inst->fee_paid > 0) {
                            $reduceFee = min($feeToReverse, (float) $inst->fee_paid);
                            $inst->fee_paid = round((float) $inst->fee_paid - $reduceFee, 2);
                            $inst->total_paid = round((float) $inst->total_paid - $reduceFee, 2);
                            $feeToReverse -= $reduceFee;
                        }

                        $paidExcludingFee = max(0, (float) $inst->total_paid - (float) $inst->fee_paid);
                        $interestPaidOnRow = min((float) $inst->interest_amount, $paidExcludingFee);
                        $principalPaidOnRow = max(0, $paidExcludingFee - $interestPaidOnRow);

                        if ($principalToReverse > 0.001 && $principalPaidOnRow > 0) {
                            $reducePrin = min($principalToReverse, $principalPaidOnRow);
                            $inst->total_paid = round((float) $inst->total_paid - $reducePrin, 2);
                            $principalToReverse -= $reducePrin;
                            $paidExcludingFee -= $reducePrin;
                        }

                        if ($interestToReverse > 0.001 && $interestPaidOnRow > 0) {
                            $reduceInt = min($interestToReverse, $interestPaidOnRow);
                            $inst->total_paid = round((float) $inst->total_paid - $reduceInt, 2);
                            $interestToReverse -= $reduceInt;
                        }

                        if ($inst->total_paid <= 0.001) {
                            $inst->repayment_transaction_id = null;
                            $inst->fee_paid = 0;
                            $inst->total_paid = 0;
                        }

                        $inst->save();

                        if ($feeToReverse <= 0.001 && $principalToReverse <= 0.001 && $interestToReverse <= 0.001) {
                            Payment::where('repayment_transaction_id', $transaction->id)
                                ->get()
                                ->each(function (Payment $payment): void {
                                    $payment->repayment_transaction_id = null;
                                    $payment->save();
                                });

                            break;
                        }
                    }
                }

                // Also delete associated Revenue records
                Revenue::where('repayment_transaction_id', $transaction->id)
                    ->get()
                    ->each(function (Revenue $revenue): void {
                        $revenue->delete();
                    });

                $transaction->delete();
            }

            $loan->recalculateSchedule();
            $loan->updateAging();

            $usesInstallmentFee = (trim((string) ($loan->admin_fee_type ?? '')) ?: 'one_time') === 'monthly';
            $completedLoanExpression = $usesInstallmentFee
                ? 'total_paid < (COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0) + COALESCE(fee_amount, 0))'
                : 'total_paid < (COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0))';
            $unpaidCount = Payment::where('loan_id', $loan->id)
                ->whereRaw($completedLoanExpression)
                ->count();

            if ($unpaidCount > 0 && $loan->status === 'completed') {
                $loan->update(['status' => 'active']);
            }

            $loan->update(['total_paid' => $loan->payments()->sum('total_paid')]);

            return [
                'loan' => $loan->fresh(),
                'transaction' => $transaction,
            ];
        });
    }
}
