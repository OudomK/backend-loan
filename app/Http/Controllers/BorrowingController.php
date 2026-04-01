<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\BorrowingRepayment;
use App\Models\Lender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BorrowingController extends Controller
{
    private function ensurePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        abort_if(!$user, 401, 'Unauthenticated.');

        $role = strtolower((string) ($user->roles()->pluck('name')->first() ?? $user->role ?? ''));

        if (in_array($role, ['admin', 'super_admin'], true) || $user->can($permission)) {
            return;
        }

        abort(403, 'You do not have permission to perform this action.');
    }

    public function getBorrowings()
    {
        $borrowings = Borrowing::with('lender', 'repayments')
            ->orderBy('borrowing_date', 'desc')
            ->get();

        return response()->json($borrowings->map(function ($b) {
            $totalPrincipalPaid = (float) $b->repayments->sum('principal_paid');
            $totalInterestPaid = (float) $b->repayments->sum('interest_paid');
            $balance = round((float) $b->amount - $totalPrincipalPaid, 2);

            return [
                'id' => $b->id,
                'lender_id' => $b->lender_id,
                'lender_code' => $b->lender->lender_code,
                'lender_name' => $b->lender->name,
                'lender_type' => $b->lender->lender_type,
                'borrowing_date' => $b->borrowing_date,
                'transaction_no' => $b->transaction_no,
                'loan_account' => $b->loan_account,
                'account_no' => $b->account_no,
                'category' => $b->category,
                'contract_no' => $b->contract_no,
                'payment_method' => $b->payment_method,
                'int_pay_mode' => $b->int_pay_mode,
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
        $this->ensurePermission($request, 'ui:savings:create');

        $validated = $request->validate([
            'lender_code' => 'required|unique:lenders',
            'name' => 'required',
            'lender_type' => 'required',
            'phone' => 'nullable',
            'address' => 'nullable',
        ]);

        $lender = Lender::create($validated);
        return response()->json($lender);
    }

    public function updateLender(Request $request, $id)
    {
        $this->ensurePermission($request, 'ui:savings:edit');

        $lender = Lender::findOrFail($id);
        $validated = $request->validate([
            'lender_code' => 'required|unique:lenders,lender_code,' . $id,
            'name' => 'required',
            'lender_type' => 'required',
            'phone' => 'nullable',
            'address' => 'nullable',
        ]);

        $lender->update($validated);
        return response()->json($lender);
    }

    public function updateBorrowing(Request $request, $id)
    {
        $this->ensurePermission($request, 'ui:savings:edit');

        $borrowing = Borrowing::with('repayments')->findOrFail($id);

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
            'term_months' => 'required|integer|min:1',
            'amount' => 'required|numeric|gt:0',
            'interest_rate' => 'required|numeric|min:0',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric|min:0',
            'first_pay_date' => 'nullable|date',
            'maturity_date' => 'nullable|date',
            'sl_term' => 'nullable',
            'late_principal' => 'nullable|numeric|min:0',
            'loan_interest' => 'nullable|numeric|min:0',
        ]);

        $totalPrincipalPaid = (float) $borrowing->repayments->sum('principal_paid');
        $newAmount = (float) $validated['amount'];

        if ($newAmount + 0.001 < $totalPrincipalPaid) {
            return response()->json([
                'message' => 'Loan amount cannot be less than the principal already repaid.',
            ], 422);
        }

        $validated['status'] = $totalPrincipalPaid + 0.001 >= $newAmount ? 'completed' : 'active';

        $borrowing->update($validated);
        return response()->json($borrowing);
    }

    public function storeBorrowing(Request $request)
    {
        $this->ensurePermission($request, 'ui:savings:create');

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
            'term_months' => 'required|integer|min:1',
            'amount' => 'required|numeric|gt:0',
            'interest_rate' => 'required|numeric|min:0',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric|min:0',
            'first_pay_date' => 'nullable|date',
            'maturity_date' => 'nullable|date',
            'sl_term' => 'nullable',
            'late_principal' => 'nullable|numeric|min:0',
            'loan_interest' => 'nullable|numeric|min:0',
        ]);

        $validated['status'] = 'active';

        $borrowing = Borrowing::create($validated);
        return response()->json($borrowing);
    }

    public function repayBorrowing(Request $request)
    {
        $this->ensurePermission($request, 'ui:savings:edit');

        $validated = $request->validate([
            'borrowing_id' => 'required|exists:borrowings,id',
            'payment_date' => 'required|date',
            'principal_paid' => 'required|numeric|min:0',
            'interest_paid' => 'required|numeric|min:0',
            'payment_method' => 'required',
            'remarks' => 'nullable',
        ]);

        $validated['total_paid'] = $validated['principal_paid'] + $validated['interest_paid'];

        return DB::transaction(function () use ($validated) {
            $borrowing = Borrowing::with('repayments')->findOrFail($validated['borrowing_id']);
            $alreadyPaid = (float) $borrowing->repayments->sum('principal_paid');
            $remainingBalance = round((float) $borrowing->amount - $alreadyPaid, 2);

            if ($remainingBalance <= 0.001) {
                return response()->json([
                    'message' => 'This borrowing is already fully repaid.',
                ], 422);
            }

            if ($validated['principal_paid'] > $remainingBalance + 0.001) {
                return response()->json([
                    'message' => "Principal paid ({$validated['principal_paid']}) exceeds remaining balance (" . number_format($remainingBalance, 2) . "). Please check the amount.",
                ], 422);
            }

            $repayment = BorrowingRepayment::create($validated);

            $totalPrincipalPaid = $alreadyPaid + (float) $validated['principal_paid'];
            $borrowing->update([
                'status' => $totalPrincipalPaid + 0.001 >= (float) $borrowing->amount ? 'completed' : 'active',
            ]);

            return response()->json($repayment);
        });
    }
}
