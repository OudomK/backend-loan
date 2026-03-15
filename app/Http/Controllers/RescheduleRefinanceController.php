<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\Request;

class RescheduleRefinanceController extends Controller
{
    protected $loanService;

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
                $q->where('loan_code', 'like', "%$query%")
                    ->orWhereHas('borrower', function ($bq) use ($query) {
                        $bq->where('first_name', 'like', "%$query%")
                            ->orWhere('last_name', 'like', "%$query%");
                    });
            })
            ->limit(10)
            ->get();

        return response()->json($loans->map(function (Loan $loan) {
            $currentBalance = $this->loanService->calculateCurrentBalance($loan);
            // Count installments not yet fully paid (per-row comparison).
            $remainingTerm = $loan->payments->filter(function ($p) {
                $due = (float) $p->principal_amount + (float) $p->interest_amount;
                return (float) $p->total_paid < $due - 0.01;
            })->count();

            return [
                'id' => $loan->id,
                'code' => $loan->loan_code,
                'name' => $loan->borrower->last_name . ' ' . $loan->borrower->first_name,
                'amount' => $loan->amount,
                'balance' => $currentBalance,
                'rate' => $loan->interest_rate,
                'term' => $loan->duration_months,
                'remainingTerm' => $remainingTerm,
                'start_date' => $loan->start_date,
                'repayment_method' => $loan->repayment_method,
                'currency' => $loan->currency,
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
            'start_date' => 'required|date',
            'repayment_method' => 'nullable|string',
        ]);

        $loan = Loan::findOrFail($validated['loan_id']);
        $schedule = $this->loanService->previewModification($loan, $validated);

        return response()->json($schedule);
    }
}