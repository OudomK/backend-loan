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
                    'name' => $loan->borrower->first_name . ' ' . $loan->borrower->last_name,
                    'code' => $loan->loan_code ?? ('L-' . str_pad($loan->id, 5, '0', STR_PAD_LEFT)),
                    'payment_date' => Carbon::parse($nextPayment->payment_date)->format('Y-m-d'),
                    'amount' => $symbol . number_format($dueAmount, 2),
                    'principal' => (string) number_format($nextPayment->principal_amount, 2),
                    'interest' => (string) number_format($nextPayment->interest_amount, 2),
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
                    'name' => $loan->borrower->first_name . ' ' . $loan->borrower->last_name,
                    'code' => $loan->loan_code ?? ('L-' . str_pad($loan->id, 5, '0', STR_PAD_LEFT)),
                    'payment_date' => Carbon::parse($payment->payment_date)->format('Y-m-d'),
                    'amount' => $symbol . number_format($dueAmount, 2),
                    'principal' => (string) number_format($payment->principal_amount, 2),
                    'interest' => (string) number_format($payment->interest_amount, 2),
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
        $query = $request->get('query');

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
                'name' => $loan->borrower->first_name . ' ' . $loan->borrower->last_name,
                'code' => $loan->loan_code ?? ('L-' . str_pad($loan->id, 5, '0', STR_PAD_LEFT)),
                'principal' => (string) $loan->amount,
                'interest' => (string) $loan->interest_rate, // Simple mapping for search
            ];
        }));
    }

    /**
     * Get unpaid installments for a specific loan.
     */
    public function getInstallments($loan_id)
    {
        $installments = Payment::where('loan_id', $loan_id)
            ->whereRaw('total_paid < (principal_amount + interest_amount)')
            ->orderBy('payment_date', 'asc')
            ->get();

        return response()->json($installments);
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
            'repayment_type' => 'required|string|in:Normal,Prepayment,Partial,Pay Off,Refinance,Reschedule,Recovery',
            'transaction_date' => 'required|date',
            'penalty_amount' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $loan = Loan::findOrFail($validated['loan_id']);
            $penaltyPaid = $validated['penalty_amount'] ?? 0;
            $remainingPaid = $validated['amount_paid'] - $penaltyPaid;

            // Fetch unpaid installments
            $installments = Payment::where('loan_id', $loan->id)
                ->whereRaw('total_paid < (principal_amount + interest_amount)')
                ->orderBy('payment_date', 'asc')
                ->get();

            if ($installments->isEmpty()) {
                throw new \Exception("No unpaid installments found for this loan.");
            }

            // Keep installment-level penalty in sync so customer history can display it.
            if ($penaltyPaid > 0) {
                $firstInstallment = $installments->first();
                $firstInstallment->penalty_amount = round(($firstInstallment->penalty_amount ?? 0) + $penaltyPaid, 2);
                $firstInstallment->save();
            }

            // Normal mode validation: must pay exactly the current installment's due (excluding penalty)
            if ($validated['repayment_type'] === 'Normal') {
                $firstInst = $installments->first();
                $dueForFirst = ($firstInst->principal_amount + $firstInst->interest_amount) - $firstInst->total_paid;
                // Allow small rounding difference
                if (abs($remainingPaid - $dueForFirst) > 0.01) {
                    throw new \Exception("Normal payment must equal the current installment due amount ($dueForFirst) plus any penalty.");
                }
            }

            // Record the transaction
            $transaction = RepaymentTransaction::create([
                'loan_id' => $loan->id,
                'collector_id' => $validated['collector_id'],
                'amount_paid' => $validated['amount_paid'],
                'principal_paid' => 0, // Will update after distribution
                'interest_paid' => 0,
                'penalty_paid' => $penaltyPaid,
                'payment_method' => $validated['payment_method'],
                'repayment_type' => $validated['repayment_type'],
                'transaction_date' => $validated['transaction_date'],
            ]);

            $totalPrincipalPaid = 0;
            $totalInterestPaid = 0;
            $totalPenaltyPaid = $penaltyPaid;

            /** @var \App\Models\Payment $inst */
            foreach ($installments as $inst) {
                if ($remainingPaid <= 0)
                    break;

                $dueInterest = $inst->interest_amount - ($inst->total_paid >= $inst->interest_amount ? $inst->interest_amount : $inst->total_paid);
                // Simple logic: total_paid tracks progress towards (princ + interest). 
                // Let's refine: assume total_paid first covers interest, then principal.

                // 1. Penalty (not handled yet in schedule, but could be added here)
                // For now, let's skip penalty distribution unless we have a penalty field in installments that is actually used.

                // 2. Interest
                $interestToPay = round(min($remainingPaid, $dueInterest), 2);
                $totalInterestPaid += $interestToPay;
                $remainingPaid -= $interestToPay;

                // 3. Principal
                if ($remainingPaid > 0.001) {
                    $duePrincipal = $inst->principal_amount - max(0, $inst->total_paid + $interestToPay - $inst->interest_amount);
                    $principalToPay = round(min($remainingPaid, $duePrincipal), 2);
                    $totalPrincipalPaid += $principalToPay;
                    $remainingPaid -= $principalToPay;
                } else {
                    $principalToPay = 0;
                }

                $inst->total_paid = round($inst->total_paid + $interestToPay + $principalToPay, 2);
                $inst->save();
            }

            // Update transaction details
            $transaction->update([
                'principal_paid' => $totalPrincipalPaid,
                'interest_paid' => $totalInterestPaid,
                'penalty_paid' => $totalPenaltyPaid,
            ]);

            // Special handling for Pay Off: If all principal is settled, mark loan as completed
            if ($validated['repayment_type'] === 'Pay Off') {
                $unpaidPrincipalCount = Payment::where('loan_id', $loan->id)
                    ->whereRaw('total_paid < principal_amount')
                    ->count();

                if ($unpaidPrincipalCount === 0) {
                    // All principal is paid. Force all installments to 'fully paid' state by setting total_paid = principal+interest
                    // This effectively waives any remaining interest.
                    Payment::where('loan_id', $loan->id)->each(function (\App\Models\Payment $p) {
                        $p->update(['total_paid' => $p->principal_amount + $p->interest_amount]);
                    });
                }
            }

            // Check if loan is completed
            $unpaidCount = Payment::where('loan_id', $loan->id)
                ->whereRaw('total_paid < (principal_amount + interest_amount)')
                ->count();

            if ($unpaidCount === 0) {
                $loan->update(['status' => 'completed']);
            }

            return response()->json([
                'message' => 'Repayment processed successfully',
                'transaction' => $transaction,
                'loan_status' => $loan->status
            ]);
        });
    }
}
