<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use App\Services\RepaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RepaymentController extends Controller
{
    private function unpaidInstallmentExpression(): string
    {
        return "total_paid < (principal_amount + interest_amount + CASE WHEN COALESCE(loans.admin_fee_type, 'one_time') = 'monthly' THEN COALESCE(fee_amount, 0) ELSE 0 END - 0.01)";
    }

    private function unpaidPaymentExpressionForLoan(Loan $loan, bool $withTolerance = false): string
    {
        $baseExpression = trim((string) ($loan->admin_fee_type ?? '')) === 'monthly'
            ? 'principal_amount + interest_amount + COALESCE(fee_amount, 0)'
            : 'principal_amount + interest_amount';

        return $withTolerance
            ? "total_paid < ({$baseExpression} - 0.01)"
            : "total_paid < ({$baseExpression})";
    }

    /**
     * Get loans due today or overdue.
     */
    public function getDueList()
    {
        $today = Carbon::today();

        $dueToday = Loan::with([
            'borrower' => function ($q) {
                $q->withTrashed();
            }
        ])
            ->where('status', 'active')
            ->whereHas('payments', function ($query) use ($today) {
                $query->where('payment_date', $today)
                    ->whereRaw($this->unpaidInstallmentExpression());
            })
            ->get();

        $formatDueToday = function ($loans) use ($today) {
            return $loans->map(function ($loan) use ($today) {
                $paymentDueExpression = $this->unpaidPaymentExpressionForLoan($loan);
                $nextPayment = $loan->payments()
                    ->whereRaw($paymentDueExpression)
                    ->where('payment_date', $today->toDateString())
                    ->orderBy('payment_date', 'asc')
                    ->first();

                if (!$nextPayment) {
                    $nextPayment = $loan->payments()
                        ->whereRaw($paymentDueExpression)
                        ->orderBy('payment_date', 'asc')
                        ->first();
                }

                $dueAmount = ($nextPayment->principal_amount + $nextPayment->interest_amount + ($nextPayment->fee_amount ?? 0)) - $nextPayment->total_paid;
                $symbol = str_contains((string) $loan->currency, 'KHR') ? '៛' : '$';

                return [
                    'id' => (string) $loan->id,
                    'name' => $loan->borrower
                        ? ($loan->borrower->first_name . ' ' . $loan->borrower->last_name)
                        : 'Unknown (Deleted)',
                    'code' => $loan->loan_code ?? ('L-' . str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT)),
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

        $overdueRows = collect();
        $overdueLoans = Loan::with([
            'borrower' => function ($q) {
                $q->withTrashed();
            }
        ])
            ->where('status', 'active')
            ->whereHas('payments', function ($query) use ($today) {
                $query->where('payment_date', '<', $today)
                    ->whereRaw($this->unpaidInstallmentExpression());
            })
            ->get();

        /** @var \App\Models\Loan $loan */
        foreach ($overdueLoans as $loan) {
            $paymentDueExpression = $this->unpaidPaymentExpressionForLoan($loan);
            $overduePayments = $loan->payments()
                ->where('payment_date', '<', $today->toDateString())
                ->whereRaw($paymentDueExpression)
                ->orderBy('payment_date', 'asc')
                ->get();

            $symbol = str_contains((string) $loan->currency, 'KHR') ? '៛' : '$';

            foreach ($overduePayments as $payment) {
                $dueAmount = ($payment->principal_amount + $payment->interest_amount + ($payment->fee_amount ?? 0)) - $payment->total_paid;
                $dpd = (int) $today->diffInDays(Carbon::parse($payment->payment_date));

                $overdueRows->push([
                    'id' => (string) $loan->id,
                    'name' => $loan->borrower
                        ? ($loan->borrower->first_name . ' ' . $loan->borrower->last_name)
                        : 'Unknown (Deleted)',
                    'code' => $loan->loan_code ?? ('L-' . str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT)),
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

        $prepaymentDays = (int) (\App\Models\Setting::where('key', 'prepayment_days')->value('value') ?? 3);

        $prepaymentLoans = Loan::with([
            'borrower' => function ($q) {
                $q->withTrashed();
            }
        ])
            ->where('status', 'active')
            ->whereHas('payments', function ($query) use ($today, $prepaymentDays) {
                $query->where('payment_date', '>', $today)
                    ->where('payment_date', '<=', $today->copy()->addDays($prepaymentDays))
                    ->whereRaw($this->unpaidInstallmentExpression());
            })
            ->whereDoesntHave('payments', function ($query) use ($today) {
                $query->where('payment_date', '<=', $today)
                    ->whereRaw($this->unpaidInstallmentExpression());
            })
            ->get();

        $formatPrepayment = function ($loans) use ($today) {
            return $loans->map(function ($loan) use ($today) {
                $paymentDueExpression = $this->unpaidPaymentExpressionForLoan($loan);
                $nextPayment = $loan->payments()
                    ->whereRaw($paymentDueExpression)
                    ->orderBy('payment_date', 'asc')
                    ->first();

                if (!$nextPayment) {
                    return null;
                }

                $dueAmount = ($nextPayment->principal_amount + $nextPayment->interest_amount + ($nextPayment->fee_amount ?? 0)) - $nextPayment->total_paid;
                $symbol = str_contains((string) $loan->currency, 'KHR') ? '៛' : '$';

                return [
                    'id' => (string) $loan->id,
                    'name' => $loan->borrower
                        ? ($loan->borrower->first_name . ' ' . $loan->borrower->last_name)
                        : 'Unknown (Deleted)',
                    'code' => $loan->loan_code ?? ('L-' . str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT)),
                    'payment_date' => Carbon::parse($nextPayment->payment_date)->format('Y-m-d'),
                    'amount' => $symbol . number_format($dueAmount, 2),
                    'principal' => (string) number_format($nextPayment->principal_amount, 2),
                    'interest' => (string) number_format($nextPayment->interest_amount, 2),
                    'installment_no' => (string) $nextPayment->payment_number,
                    'dpd' => '0',
                    'symbol' => $symbol,
                ];
            })->filter()->values()->all();
        };

        return response()->json([
            'due_today' => $formatDueToday($dueToday),
            'overdue' => $overdueRows->values()->all(),
            'prepayment' => $formatPrepayment($prepaymentLoans),
        ]);
    }

    /**
     * Search for active loans.
     */
    public function search(Request $request)
    {
        $query = trim((string) $request->input('query', ''));
        if ($query === '') {
            return response()->json([]);
        }

        $like = '%' . $query . '%';

        $loans = Loan::with([
            'borrower' => function ($q) {
                $q->withTrashed();
            }
        ])
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $like = '%' . $query . '%';
                $q->where('loan_code', 'LIKE', $like)
                    ->orWhereHas('borrower', function ($bq) use ($like) {
                        $bq->where('first_name', 'LIKE', $like)
                            ->orWhere('last_name', 'LIKE', $like)
                            ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", [$like])
                            ->orWhereRaw("CONCAT(COALESCE(last_name, ''), ' ', COALESCE(first_name, '')) LIKE ?", [$like]);
                    });
            })
            ->limit(10)
            ->get();

        return response()->json($loans->map(function ($loan) {
            return [
                'id' => (string) $loan->id,
                'name' => $loan->borrower
                    ? ($loan->borrower->first_name . ' ' . $loan->borrower->last_name)
                    : 'Unknown (Deleted)',
                'code' => $loan->loan_code ?? ('L-' . str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT)),
                'principal' => (string) $loan->amount,
                'interest' => (string) $loan->interest_rate,
            ];
        }));
    }

    /**
     * Get unpaid installments for a specific loan and fee status (for one-time fee display).
     */
    public function getInstallments(int|string $loan_id)
    {
        $loan = Loan::find($loan_id);
        $feeType = $loan ? (trim((string) ($loan->admin_fee_type ?? '')) ?: 'one_time') : 'one_time';
        $usesInstallmentFee = $feeType === 'monthly';
        $installmentDueExpression = $loan
            ? $this->unpaidPaymentExpressionForLoan($loan)
            : 'total_paid < (principal_amount + interest_amount)';
        $installments = Payment::where('loan_id', $loan_id)
            ->whereRaw($installmentDueExpression)
            ->orderBy('payment_date', 'asc')
            ->get();

        $totalFee = $usesInstallmentFee && $loan
            ? ($loan->amount * ((float) ($loan->admin_fee ?? 0) / 100))
            : 0;
        $feePaidSoFar = $usesInstallmentFee
            ? (float) RepaymentTransaction::where('loan_id', $loan_id)->sum('fee_paid')
            : 0;

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
    public function store(Request $request, RepaymentService $repaymentService)
    {
        $validated = $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'collector_id' => 'required|exists:loan_officers,id',
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'repayment_type' => 'required|string|in:Normal,Prepayment,Partial,Pay Off,Refinance,Reschedule,Recovery,Withdraw',
            'transaction_date' => 'required|date',
            'penalty_amount' => 'nullable|numeric|min:0',
            'penalty_due' => 'nullable|numeric|min:0',
            'fee_amount' => 'nullable|numeric|min:0',
            'waived_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $repaymentService->process($validated);

            return response()->json([
                'message' => 'Repayment processed successfully',
                'transaction' => $result['transaction'],
                'loan_status' => $result['loan']->status,
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function destroy(int|string $id, RepaymentService $repaymentService)
    {
        try {
            $result = $repaymentService->void((int) $id);

            return response()->json([
                'message' => 'Transaction voided successfully',
                'loan_status' => $result['loan']->status,
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
