<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\Request;

class RescheduleRefinanceController extends Controller
{
    protected LoanService $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    public function searchActiveLoans(Request $request)
    {
        $query = $request->input('query');

        $loans = Loan::with(['borrower', 'payments'])
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $like = "%{$query}%";
                $queryNoSpace = str_replace(' ', '', $query);
                $likeNoSpace = "%{$queryNoSpace}%";

                $q->where('loan_code', 'like', $like)
                    ->orWhereHas('borrower', function ($bq) use ($like, $likeNoSpace) {
                        $bq->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('latin_name', 'like', $like)
                    ->orWhere('nickname', 'like', $like)
                            ->orWhere('id_number', 'like', $like)
                            ->orWhere(\Illuminate\Support\Facades\DB::raw("REPLACE(id_number, ' ', '')"), 'like', $likeNoSpace)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere(\Illuminate\Support\Facades\DB::raw("REPLACE(phone, ' ', '')"), 'like', $likeNoSpace)
                            ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(last_name, ' ', first_name)"), 'like', $like)
                            ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', $like);
                    });
            })
            ->whereHas('payments', function ($query) {
                $query->whereRaw("total_paid < (principal_amount + interest_amount + CASE WHEN COALESCE(loans.admin_fee_type, 'one_time') = 'monthly' THEN COALESCE(fee_amount, 0) ELSE 0 END - 0.01)");
            })
            ->get();

        return response()->json($loans->map(function (Loan $loan) {
            $currentBalance = $this->loanService->calculateCurrentBalance($loan);
            // Count installments not yet fully paid (per-row comparison).
            $remainingTerm = $loan->payments->filter(function ($p) {
                $due = (float) $p->principal_amount + (float) $p->interest_amount;
                return (float) $p->total_paid < $due - 0.01;
            })->count();

            // Calculate one-month (next unpaid) interest
            $nextUnpaidPayment = $loan->payments->filter(function ($p) {
                $due = (float) $p->principal_amount + (float) $p->interest_amount + (float) $p->penalty_amount;
                return (float) $p->total_paid < $due - 0.01;
            })->first();
            $accruedInterest = 0;
            if ($nextUnpaidPayment) {
                $feePaid = $nextUnpaidPayment->fee_paid ?? 0;
                $alreadyPaidToPrinInt = max(0, (float) $nextUnpaidPayment->total_paid - (float) $feePaid);
                $interestPaidSoFar = min((float) $nextUnpaidPayment->interest_amount, $alreadyPaidToPrinInt);
                $accruedInterest = max(0, (float) $nextUnpaidPayment->interest_amount - $interestPaidSoFar);
            }

            $penaltyDue = $loan->currentPenaltyDue();
            return [
                'id' => $loan->id,
                'code' => $loan->loan_code,
                'name' => $loan->borrower->first_name . ' ' . $loan->borrower->last_name,
                'first_name' => $loan->borrower->first_name,
                'last_name' => $loan->borrower->last_name,
                'gender' => $loan->borrower->gender,
                'phone' => $loan->borrower->phone,
                'id_card_number' => $loan->borrower->id_number,
                'address' => $loan->borrower->address,
                'village' => $loan->borrower->village,
                'commune' => $loan->borrower->commune,
                'district' => $loan->borrower->district,
                'province' => $loan->borrower->province,
                'amount' => $loan->amount,
                'balance' => $currentBalance,
                'rate' => $loan->interest_rate,
                'term' => $loan->duration_months,
                'remainingTerm' => $remainingTerm,
                'accruedInterest' => $accruedInterest,
                'penaltyDue' => $penaltyDue,
                'paid_count' => $loan->payments->where('total_paid', '>', 0)->count(),
                'start_date' => $loan->start_date,
                'repayment_method' => $loan->repayment_method,
                'currency' => $loan->currency,
                'loan_cycle' => $loan->loan_cycle,
                'loan_code_cycle' => $loan->loan_code_cycle,
                'payment_qr' => $loan->paymentQr ? $loan->paymentQr->toArray() : null,
            ];
        }));
    }

    public function reschedule(Request $request)
    {
        $validated = $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'new_rate' => 'required|numeric|min:0',
            'remaining_term' => 'required|integer|min:1',
            'reschedule_date' => 'required|date',
            'first_payment_date' => 'nullable|date',
            'repayment_method' => 'nullable|string',
            'reschedule_fee' => 'nullable|numeric|min:0',
            'pay_off_principal' => 'nullable|numeric|min:0',
            'accrued_interest' => 'nullable|numeric|min:0',
        ]);

        $loan = Loan::findOrFail($validated['loan_id']);
        $updatedLoan = $this->loanService->reschedule($loan, $validated);

        return response()->json([
            'message' => 'Loan rescheduled successfully',
            'loan' => $updatedLoan->load('payments')
        ]);
    }

    public function refinance(Request $request)
    {
        $validated = $request->validate([
            'old_loan_id' => 'required|exists:loans,id',
            'additional_amount' => 'required|numeric',
            'new_rate' => 'required|numeric',
            'new_term' => 'required|integer',
            'start_date' => 'required|date',
            'refinance_fee' => 'nullable|numeric',
            'penalty_amount' => 'nullable|numeric|min:0',
            'repayment_method' => 'nullable|string',
        ]);

        $oldLoan = Loan::findOrFail($validated['old_loan_id']);
        $newLoan = $this->loanService->refinance($oldLoan, $validated);

        return response()->json([
            'message' => 'Loan refinanced successfully',
            'new_loan_id' => $newLoan->id
        ]);
    }

    public function previewModification(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:reschedule,refinance',
            'loan_id' => 'required|exists:loans,id',
            'new_rate' => 'required|numeric',
            'term' => 'required|integer', // remaining_term for reschedule, new_term for refinance
            'additional_amount' => 'nullable|numeric',
            'paydown_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'repayment_method' => 'nullable|string',
        ]);

        $loan = Loan::findOrFail($validated['loan_id']);
        $schedule = $this->loanService->previewModification($loan, $validated);

        return response()->json($schedule);
    }
}