<?php

namespace App\Http\Controllers;

use App\Models\SavingAccount;
use App\Models\SavingTransaction;
use Illuminate\Http\Request;

class SavingAccountController extends Controller
{
    public function index()
    {
        $savings = SavingAccount::with('saver', 'lender')->get();
        return $savings->map(function ($a) {
            return [
                'id' => $a->id,
                'account_number' => $a->account_number,
                'customer_name' => $a->saver ? $a->saver->name : 'N/A',
                'lender_name' => $a->lender ? $a->lender->name : '-',
                'lender_code' => $a->lender ? $a->lender->code : '-',
                'lender_type' => $a->lender ? ($a->lender->customer_type ?? 'Individual') : 'Individual',
                'balance' => $a->balance,
                'currency' => $a->currency,
                'interest_rate' => $a->interest_rate,
                'term' => $a->term,
                'maturity_date' => $a->maturity_date,
                'status' => $a->status,
                'account_type' => $a->account_type,

                // Borrowing Legacy Fields
                'transaction_no' => $a->transaction_no,
                'loan_account' => $a->loan_account,
                'category' => $a->category,
                'borrowing_date' => $a->borrowing_date,
                'account_no' => $a->account_no,
                'contract_no' => $a->contract_no,
                'payment_method' => $a->payment_method,
                'first_pay_date' => $a->first_pay_date,
                'term_months' => $a->term_months,
                'amount' => $a->amount,
                'int_pay_mode' => $a->int_pay_mode,
                'fee' => $a->fee,
                'sl_term' => $a->sl_term,
                'late_principal' => $a->late_principal,
                'loan_interest' => $a->loan_interest,

                'total_deposits' => $a->total_deposits,
                'total_withdrawals' => $a->total_withdrawals,
                'interest_earned' => $a->interest_earned,
            ];
        });
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'saver_id' => 'nullable|exists:savers,id', // or lender_id depending on flow
            'lender_id' => 'nullable|exists:lenders,id',
            'account_type' => 'required|string',
            'currency' => 'required|string',
            'interest_rate' => 'nullable|numeric',
            'term' => 'nullable|string',
            'maturity_date' => 'nullable|date',
            'initial_deposit' => 'nullable|numeric',
            'balance' => 'nullable|numeric', // Support direct balance injection if needed

            // Borrowing Fields
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'category' => 'nullable|string',
            'borrowing_date' => 'nullable|date',
            'account_no' => 'nullable|string',
            'contract_no' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'first_pay_date' => 'nullable|date',
            'term_months' => 'nullable|integer',
            'amount' => 'nullable|numeric',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric',
            'sl_term' => 'nullable|string',
            'late_principal' => 'nullable|numeric',
            'loan_interest' => 'nullable|numeric',
        ]);

        $account = new SavingAccount();
        // If it's a borrowing saving, we might use lender_id instead of saver_id or both
        $account->saver_id = $validated['saver_id'] ?? null;
        $account->lender_id = $validated['lender_id'] ?? null;

        $account->account_number = $validated['account_no'] ?? $this->generateAccountNumber(); // Use account_no if provided
        $account->account_type = $validated['account_type'];
        $account->currency = $validated['currency'];
        $account->interest_rate = $validated['interest_rate'] ?? 0;
        $account->term = $validated['term'] ?? $validated['term_months'];
        $account->maturity_date = $validated['maturity_date'];
        $account->status = 'Active';

        // Borrowing assignment
        $account->transaction_no = $validated['transaction_no'];
        $account->loan_account = $validated['loan_account'];
        $account->category = $validated['category'] ?? 'Loan Capital';
        $account->borrowing_date = $validated['borrowing_date'];
        $account->account_no = $validated['account_no'];
        $account->contract_no = $validated['contract_no'];
        $account->payment_method = $validated['payment_method'];
        $account->first_pay_date = $validated['first_pay_date'];
        $account->term_months = $validated['term_months'] ?? 0;
        $account->amount = $validated['amount'] ?? 0;
        $account->int_pay_mode = $validated['int_pay_mode'];
        $account->fee = $validated['fee'] ?? 0;
        $account->sl_term = $validated['sl_term'];
        $account->late_principal = $validated['late_principal'] ?? 0;
        $account->loan_interest = $validated['loan_interest'] ?? 0;

        // Balance handling
        $initialDeposit = $validated['balance'] ?? $validated['initial_deposit'] ?? 0;
        $account->balance = $initialDeposit;
        $account->total_deposits = $initialDeposit;

        $account->save();

        if ($initialDeposit > 0) {
            $transaction = new SavingTransaction();
            $transaction->saving_account_id = $account->id;
            $transaction->transaction_type = 'Deposit';
            $transaction->amount = $initialDeposit;
            $transaction->transaction_date = now();
            $transaction->payment_method = $validated['payment_method'] ?? 'Cash';
            $transaction->performed_by = 'System';
            $transaction->save();
        }

        return response()->json($account, 201);
    }

    public function update(Request $request, $id)
    {
        $account = SavingAccount::findOrFail($id);

        $validated = $request->validate([
            'saver_id' => 'nullable|exists:savers,id',
            'lender_id' => 'nullable|exists:lenders,id',
            'account_type' => 'nullable|string',
            'currency' => 'nullable|string',
            'interest_rate' => 'nullable|numeric',
            'term' => 'nullable|string',
            'maturity_date' => 'nullable|date',
            'status' => 'nullable|string',
            'balance' => 'nullable|numeric',

            // Borrowing Fields
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'category' => 'nullable|string',
            'borrowing_date' => 'nullable|date',
            'account_no' => 'nullable|string',
            'contract_no' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'first_pay_date' => 'nullable|date',
            'term_months' => 'nullable|integer',
            'amount' => 'nullable|numeric',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric',
            'sl_term' => 'nullable|string',
            'late_principal' => 'nullable|numeric',
            'loan_interest' => 'nullable|numeric',
        ]);

        $account->update($validated);

        return response()->json($account);
    }

    private function generateAccountNumber()
    {
        return 'SA-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}
