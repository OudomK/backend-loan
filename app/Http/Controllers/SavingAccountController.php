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
            'currency' => 'required|string|max:20',
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
            $account->lockForUpdate(); // Prevent race conditions

            // Reload account to get latest balance after lock
            $freshAccount = $account->fresh();

            $freshAccount->increment('balance', $validated['amount']);

            $transaction = SavingTransaction::create([
                'saving_account_id' => $freshAccount->id,
                'transaction_type' => 'Deposit',
                'amount' => $validated['amount'],
                'currency' => $freshAccount->currency,
                'transaction_date' => now(),
                'reference_no' => $validated['reference_no'],
                'description' => $validated['description'],
            ]);

            return response()->json(['message' => 'Deposit successful', 'balance' => $freshAccount->balance]);
        });
    }

    public function withdraw(Request $request, SavingAccount $account)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reference_no' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($account, $validated) {
            $account->lockForUpdate(); // Prevent race conditions

            // Reload account to get latest balance after lock
            $freshAccount = $account->fresh();

            if ($freshAccount->balance < $validated['amount']) {
                throw new \Exception('Insufficient balance'); // Will rollback transaction
            }

            $freshAccount->decrement('balance', $validated['amount']);

            $transaction = SavingTransaction::create([
                'saving_account_id' => $freshAccount->id,
                'transaction_type' => 'Withdrawal',
                'amount' => $validated['amount'],
                'currency' => $freshAccount->currency,
                'transaction_date' => now(),
                'reference_no' => $validated['reference_no'],
                'description' => $validated['description'],
            ]);

            return response()->json(['message' => 'Withdrawal successful', 'balance' => $freshAccount->balance]);
        });
    }

    public function getSavingReport()
    {
        $accounts = SavingAccount::with(['borrower', 'transactions'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($accounts->map(function ($account) {
            $deposits = $account->transactions->where('transaction_type', 'Deposit');
            $withdrawals = $account->transactions->where('transaction_type', 'Withdrawal');
            $interests = $account->transactions->where('transaction_type', 'Interest');
            $lastTrans = $account->transactions->sortByDesc('transaction_date')->first();

            return [
                // Account Info
                'account_id' => $account->id,
                'account_number' => $account->account_number,
                'saver_code' => $account->borrower->borrower_code ?? '-',
                'saver_name' => $account->borrower
                    ? trim($account->borrower->first_name . ' ' . $account->borrower->last_name)
                    : 'Unknown',
                'account_type' => $account->account_type,
                'currency' => $account->currency,
                'term' => $account->term,
                'maturity_date' => $account->maturity_date,
                'created_at' => $account->created_at,

                // Financial Info
                'opening_balance' => $deposits->first()->amount ?? 0,
                'current_balance' => $account->balance,
                'interest_rate' => $account->interest_rate,
                'total_deposits' => $deposits->sum('amount'),
                'total_withdrawals' => $withdrawals->sum('amount'),
                'interest_earned' => $interests->sum('amount'),

                // Status & Activity
                'status' => $account->status,
                'last_transaction_date' => $lastTrans->transaction_date ?? null,
                'transaction_count' => $account->transactions->count(),
            ];
        }));
    }
    public function postInterest(Request $request)
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('app:post-monthly-interest');
            $output = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'message' => 'Interest calculation triggered successfully',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to calculate interest: ' . $e->getMessage()
            ], 500);
        }
    }
}
