<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\MiscellaneousTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MiscellaneousTransactionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = MiscellaneousTransaction::query()
                ->with('expenseCategory')
                ->orderByDesc('transaction_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            if ($request->has('from_date') && $request->has('to_date')) {
                $query->whereBetween('transaction_date', [$request->from_date, $request->to_date]);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('currency') && $request->currency !== 'all') {
                $query->where('currency', $request->currency);
            }

            return response()->json([
                'success' => true,
                'data' => $query->get(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching miscellaneous transactions: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'loan_id' => 'nullable|exists:loans,id',
            'type' => 'required|in:revenue,expense',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'category' => 'nullable|string',
            'amount' => 'required|numeric',
            'currency' => 'required|string',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $payload = $request->all();
            $type = strtolower((string) ($payload['type'] ?? 'expense'));

            if ($type === 'expense' && filled($payload['expense_category_id'] ?? null)) {
                $expenseCategory = ExpenseCategory::query()->findOrFail((int) $payload['expense_category_id']);
                $payload['category'] = $expenseCategory->name;
            }

            if (blank($payload['category'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category is required.',
                ], 422);
            }

            $transaction = MiscellaneousTransaction::create($payload);
            return response()->json([
                'success' => true,
                'data' => $transaction->load('expenseCategory'),
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error creating miscellaneous transaction: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $transaction = MiscellaneousTransaction::findOrFail($id);
            $transaction->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
