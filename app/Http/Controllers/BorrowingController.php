<?php

namespace App\Http\Controllers;

use App\Models\Lender;
use App\Models\Borrowing;
use App\Models\BorrowingRepayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BorrowingController extends Controller
{
    public function getBorrowings()
    {
        $borrowings = Borrowing::with('lender', 'repayments')
            ->orderBy('borrowing_date', 'desc')
            ->get();

        return response()->json($borrowings->map(function ($b) {
            $totalPrincipalPaid = $b->repayments->sum('principal_paid');
            $totalInterestPaid = $b->repayments->sum('interest_paid');
            $balance = $b->amount - $totalPrincipalPaid;

            return [
                'id' => $b->id,
                'lender_code' => $b->lender->lender_code,
                'lender_name' => $b->lender->name,
                'lender_type' => $b->lender->lender_type,
                'borrowing_date' => $b->borrowing_date,
                'account_no' => $b->account_no,
                'category' => $b->category,
                'contract_no' => $b->contract_no,
                'payment_method' => $b->payment_method,
                'first_pay_date' => $b->first_pay_date,
                'currency' => $b->currency,
                'term_months' => $b->term_months,
                'amount' => $b->amount,
                'interest_rate' => $b->interest_rate,
                'fee' => $b->fee,
                'maturity_date' => $b->maturity_date,
                'sl_term' => $b->sl_term,
                'balance' => $balance,
                'total_paid' => $totalPrincipalPaid + $totalInterestPaid,
                'status' => $b->status,
                'late_principal' => $b->late_principal ?? 0,
                'loan_interest' => $b->loan_interest ?? 0,
            ];
        }));
    }

    public function getLenders()
    {
        return response()->json(Lender::orderBy('name')->get());
    }

    public function storeLender(Request $request)
    {
        $validated = $request->validate([
            'lender_code' => 'required|unique:lenders',
            'name' => 'required',
            'lender_type' => 'required',
            'phone' => 'nullable',
            'address' => 'nullable'
        ]);

        $lender = Lender::create($validated);
        return response()->json($lender);
    }

    public function updateBorrowing(Request $request, $id)
    {
        $borrowing = Borrowing::findOrFail($id);

        $validated = $request->validate([
            'lender_id' => 'required|exists:lenders,id',
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'category' => 'required|in:Real Capital,Loan Capital',
            'borrowing_date' => 'required|date',
            'account_no' => 'nullable',
            'contract_no' => 'nullable|string',
            'payment_method' => 'required',
            'currency' => 'required',
            'term_months' => 'required|integer',
            'amount' => 'required|numeric',
            'interest_rate' => 'required|numeric',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric',
            'first_pay_date' => 'nullable|date',
            'maturity_date' => 'nullable|date',
            'sl_term' => 'nullable',
            'late_principal' => 'nullable|numeric',
            'loan_interest' => 'nullable|numeric',
        ]);

        $borrowing->update($validated);
        return response()->json($borrowing);
    }

    public function storeBorrowing(Request $request)
    {
        $validated = $request->validate([
            'lender_id' => 'required|exists:lenders,id',
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'category' => 'required|in:Real Capital,Loan Capital',
            'borrowing_date' => 'required|date',
            'account_no' => 'nullable',
            'contract_no' => 'nullable|string',
            'payment_method' => 'required',
            'currency' => 'required',
            'term_months' => 'required|integer',
            'amount' => 'required|numeric',
            'interest_rate' => 'required|numeric',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric',
            'first_pay_date' => 'nullable|date',
            'maturity_date' => 'nullable|date',
            'sl_term' => 'nullable',
            'late_principal' => 'nullable|numeric',
            'loan_interest' => 'nullable|numeric',
        ]);

        $borrowing = Borrowing::create($validated);
        return response()->json($borrowing);
    }

    public function repayBorrowing(Request $request)
    {
        $validated = $request->validate([
            'borrowing_id' => 'required|exists:borrowings,id',
            'payment_date' => 'required|date',
            'principal_paid' => 'required|numeric',
            'interest_paid' => 'required|numeric',
            'payment_method' => 'required',
            'remarks' => 'nullable',
        ]);

        $validated['total_paid'] = $validated['principal_paid'] + $validated['interest_paid'];

        return DB::transaction(function () use ($validated) {
            $repayment = BorrowingRepayment::create($validated);

            // Check if fully paid
            $borrowing = Borrowing::with('repayments')->find($validated['borrowing_id']);
            $totalPrincipalPaid = $borrowing->repayments->sum('principal_paid');

            if ($totalPrincipalPaid >= $borrowing->amount) {
                $borrowing->update(['status' => 'completed']);
            }

            return response()->json($repayment);
        });
    }
}
