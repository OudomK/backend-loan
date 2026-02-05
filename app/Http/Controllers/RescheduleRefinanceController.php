<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RescheduleRefinanceController extends Controller
{
    protected $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    public function searchActiveLoans(Request $request)
    {
        $query = $request->get('query');

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

            return [
                'id' => $loan->id,
                'code' => $loan->loan_code,
                'name' => $loan->borrower->first_name . ' ' . $loan->borrower->last_name,
                'amount' => $loan->amount,
                'balance' => $currentBalance,
                'rate' => $loan->interest_rate,
                'term' => $loan->duration_months,
                'remainingTerm' => $loan->payments->where('total_paid', '<', DB::raw('principal_amount + interest_amount'))->count(),
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
            'new_rate' => 'required|numeric',
            'extend_months' => 'required|integer',
            'reschedule_date' => 'required|date',
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
        ]);

        $oldLoan = Loan::findOrFail($validated['old_loan_id']);
        $newLoan = $this->loanService->refinance($oldLoan, $validated);

        return response()->json([
            'message' => 'Loan refinanced successfully',
            'new_loan_id' => $newLoan->id
        ]);
    }
}