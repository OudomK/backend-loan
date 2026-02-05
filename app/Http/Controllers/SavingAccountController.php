<?php

namespace App\Http\Controllers;

use App\Models\SavingAccount;
use App\Models\SavingTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavingAccountController extends Controller
{
    public function index()
    {
        return response()->json(SavingAccount::with('borrower')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'borrower_id' => 'required|exists:borrowers,id',
            'account_number' => 'required|unique:saving_accounts',
            'account_type' => 'required|in:Daily Saving,Goal Saving,Fixed Deposit',
            'currency' => 'required|string|max:3',
            'interest_rate' => 'required|numeric',
            'balance' => 'required|numeric',
            'term' => 'nullable|string',
            'maturity_date' => 'nullable|date',
            'status' => 'required|in:Active,Dormant,Closed',
        ]);

        return DB::transaction(function () use ($validated) {
            $account = SavingAccount::create($validated);

            // Create initial deposit transaction
            if ($validated['balance'] > 0) {
                SavingTransaction::create([
                    'saving_account_id' => $account->id,
                    'transaction_type' => 'Deposit',
                    'amount' => $validated['balance'],
                    'currency' => $validated['currency'],
                    'transaction_date' => now(),
                    'description' => 'Initial deposit',
                ]);
            }

            return response()->json($account->load('borrower'), 201);
        });
    }

    public function update(Request $request, SavingAccount $account)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:Active,Dormant,Closed',
            'interest_rate' => 'sometimes|numeric',
        ]);

        $account->update($validated);
        return response()->json($account);
    }

    public function deposit(Request $request, SavingAccount $account)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reference_no' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($account, $validated) {
            $account->increment('balance', $validated['amount']);

            $transaction = SavingTransaction::create([
                'saving_account_id' => $account->id,
                'transaction_type' => 'Deposit',
                'amount' => $validated['amount'],
                'currency' => $account->currency,
                'transaction_date' => now(),
                'reference_no' => $validated['reference_no'],
                'description' => $validated['description'],
            ]);

            return response()->json(['message' => 'Deposit successful', 'balance' => $account->balance]);
        });
    }

    public function withdraw(Request $request, SavingAccount $account)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reference_no' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($account->balance < $validated['amount']) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        return DB::transaction(function () use ($account, $validated) {
            $account->decrement('balance', $validated['amount']);

            $transaction = SavingTransaction::create([
                'saving_account_id' => $account->id,
                'transaction_type' => 'Withdrawal',
                'amount' => $validated['amount'],
                'currency' => $account->currency,
                'transaction_date' => now(),
                'reference_no' => $validated['reference_no'],
                'description' => $validated['description'],
            ]);

            return response()->json(['message' => 'Withdrawal successful', 'balance' => $account->balance]);
        });
    }
}
