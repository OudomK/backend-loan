<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use App\Services\RepaymentService;
use App\Services\RepaymentPreviewService;
use App\Support\SearchResultRanker;
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

    private function formatLoans(\Illuminate\Support\Collection $loans, Carbon $today): array
    {
        return $loans->map(function ($loan) use ($today) {
            $paymentDueExpression = $this->unpaidPaymentExpressionForLoan($loan);
            $nextPayment = $loan->payments()
                ->whereRaw($paymentDueExpression)
                ->where('payment_date', '<=', $today->toDateString()) // Adjust to match either
                ->orderBy('payment_date', 'asc')
                ->first();

            if (!$nextPayment) {
                $nextPayment = $loan->payments()
                    ->whereRaw($paymentDueExpression)
                    ->orderBy('payment_date', 'asc')
                    ->first();
            }

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
                'loan_amount' => (string) $loan->amount,
                'interest' => (string) number_format($nextPayment->interest_amount, 2),
                'installment_no' => (string) $nextPayment->payment_number,
                'dpd' => '0',
                'symbol' => $symbol,
                'loan_officer_id' => (string) $loan->loan_officer_id,
                'village' => (string) optional($loan->borrower)->village,
                'commune' => (string) optional($loan->borrower)->commune,
                'district' => (string) optional($loan->borrower)->district,
                'province' => (string) optional($loan->borrower)->province,
            ];
        })->filter()->values()->all();
    }

    private function getDueTodayLoans(Carbon $today): \Illuminate\Database\Eloquent\Collection
    {
        return Loan::with(['borrower' => fn($q) => $q->withTrashed()])
            ->where('status', 'active')
            ->whereHas('payments', function ($query) use ($today) {
                $query->where('payment_date', $today)
                    ->whereRaw($this->unpaidInstallmentExpression());
            })
            ->get();
    }

    private function getOverdueLoans(Carbon $today): \Illuminate\Database\Eloquent\Collection
    {
        return Loan::with(['borrower' => fn($q) => $q->withTrashed()])
            ->where('status', 'active')
            ->whereHas('payments', function ($query) use ($today) {
                $query->where('payment_date', '<', $today)
                    ->whereRaw($this->unpaidInstallmentExpression());
            })
            ->get();
    }

    private function formatOverdueLoans(\Illuminate\Support\Collection $overdueLoans, Carbon $today): array
    {
        $overdueRows = collect();

        /** @var Loan $loan */
        foreach ($overdueLoans as $loan) {
            $paymentDueExpression = $this->unpaidPaymentExpressionForLoan($loan);
            $overduePayments = $loan->payments()
                ->where('payment_date', '<', $today->toDateString())
                ->whereRaw($paymentDueExpression)
                ->orderBy('payment_date', 'asc')
                ->get();

            $symbol = str_contains((string) $loan->currency, 'KHR') ? '៛' : '$';

            $firstOverduePayment = $overduePayments->first();
            if (! $firstOverduePayment) {
                continue;
            }

            $dueAmount = $overduePayments->sum(function (Payment $payment): float {
                return max(0, (float) $payment->principal_amount + (float) $payment->interest_amount
                    + (float) ($payment->fee_amount ?? 0) - (float) $payment->total_paid);
            });

            $overdueRows->push([
                'id' => (string) $loan->id,
                'name' => $loan->borrower
                    ? ($loan->borrower->first_name . ' ' . $loan->borrower->last_name)
                    : 'Unknown (Deleted)',
                'code' => $loan->loan_code ?? ('L-' . str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT)),
                'payment_date' => Carbon::parse($firstOverduePayment->payment_date)->format('Y-m-d'),
                'amount' => $symbol . number_format($dueAmount, 2),
                'principal' => (string) number_format($firstOverduePayment->principal_amount, 2),
                'loan_amount' => (string) $loan->amount,
                'interest' => (string) number_format($firstOverduePayment->interest_amount, 2),
                'installment_no' => (string) $firstOverduePayment->payment_number,
                'dpd' => (string) $loan->currentAging($today),
                'symbol' => $symbol,
                'loan_officer_id' => (string) $loan->loan_officer_id,
                'village' => (string) optional($loan->borrower)->village,
                'commune' => (string) optional($loan->borrower)->commune,
                'district' => (string) optional($loan->borrower)->district,
                'province' => (string) optional($loan->borrower)->province,
            ]);
        }
        return $overdueRows->values()->all();
    }

    private function getPrepaymentLoans(Carbon $today): \Illuminate\Database\Eloquent\Collection
    {
        $prepaymentDays = (int) (\App\Models\Setting::where('key', 'prepayment_days')->value('value') ?? 3);

        return Loan::with(['borrower' => fn($q) => $q->withTrashed()])
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
    }

    /**
     * Get loans due today or overdue.
     */
    public function getDueList(): \Illuminate\Http\JsonResponse
    {
        $today = Carbon::today();

        $dueTodayList = $this->formatLoans($this->getDueTodayLoans($today), $today);
        $overdueList = $this->formatOverdueLoans($this->getOverdueLoans($today), $today);
        $prepaymentList = $this->formatLoans($this->getPrepaymentLoans($today), $today);

        $combined = collect($dueTodayList)
            ->merge($overdueList)
            ->merge($prepaymentList)
            ->sortBy('payment_date')
            ->values()
            ->all();

        return response()->json([
            'combined' => $combined,
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
            ->whereIn('status', ['active', 'written_off'])
            ->where(function ($q) use ($query) {
                $like = '%' . $query . '%';
                $q->where('loan_code', 'LIKE', $like)
                    ->orWhereHas('borrower', function ($bq) use ($like) {
                        $bq->where('first_name', 'LIKE', $like)
                            ->orWhere('last_name', 'LIKE', $like)
                            ->orWhere('latin_name', 'LIKE', $like)
                            ->orWhere('nickname', 'LIKE', $like)
                            ->orWhere('phone', 'LIKE', $like)
                            ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", [$like])
                            ->orWhereRaw("CONCAT(COALESCE(last_name, ''), ' ', COALESCE(first_name, '')) LIKE ?", [$like]);
                    });
            })
            ->whereHas('payments', function ($query) {
                $query->whereRaw($this->unpaidInstallmentExpression());
            })
            ->limit(50)
            ->get()
            ->sort(function (Loan $left, Loan $right) use ($query): int {
                $score = fn (Loan $loan): int => SearchResultRanker::score($query, [
                    $loan->loan_code,
                    $loan->borrower?->first_name,
                    $loan->borrower?->last_name,
                    $loan->borrower?->latin_name,
                    $loan->borrower?->nickname,
                    trim(($loan->borrower?->first_name ?? '').' '.($loan->borrower?->last_name ?? '')),
                    trim(($loan->borrower?->last_name ?? '').' '.($loan->borrower?->first_name ?? '')),
                    $loan->borrower?->phone,
                ]);

                return $score($left) <=> $score($right)
                    ?: strnatcasecmp((string) $left->loan_code, (string) $right->loan_code);
            })
            ->take(20)
            ->values();

        return response()->json($loans->map(function ($loan) {
            $symbol = str_contains(strtoupper((string) $loan->currency), 'KHR') ? '៛' : '$';
            return [
                'id' => (string) $loan->id,
                'name' => $loan->borrower
                    ? ($loan->borrower->first_name . ' ' . $loan->borrower->last_name)
                    : 'Unknown (Deleted)',
                'code' => $loan->loan_code ?? ('L-' . str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT)),
                'principal' => (string) $loan->amount,
                'loan_amount' => (string) $loan->amount,
                'interest' => (string) $loan->interest_rate,
                'currency' => (string) $loan->currency,
                'symbol' => $symbol,
                'loan_officer_id' => (string) $loan->loan_officer_id,
                'village' => (string) optional($loan->borrower)->village,
                'commune' => (string) optional($loan->borrower)->commune,
                'district' => (string) optional($loan->borrower)->district,
                'province' => (string) optional($loan->borrower)->province,
                'status' => (string) $loan->status,
            ];
        }));
    }

    /**
     * Get unpaid installments for a specific loan and fee status (for one-time fee display).
     */
    public function getInstallments(Request $request, int|string $loan_id, RepaymentPreviewService $previewService)
    {
        $validated = $request->validate([
            'transaction_date' => 'nullable|date',
            'repayment_type' => 'nullable|string|in:Normal,Prepayment,Partial,Pay Off,Refinance,Reschedule,Recovery,Withdraw',
        ]);
        $loan = Loan::findOrFail($loan_id);
        $transactionDate = Carbon::parse($validated['transaction_date'] ?? Carbon::today())->startOfDay();

        return response()->json($previewService->build(
            $loan,
            $transactionDate,
            $validated['repayment_type'] ?? 'Normal'
        ));
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
