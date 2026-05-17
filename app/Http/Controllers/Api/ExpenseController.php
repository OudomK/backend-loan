<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Expense;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with('expenseCategory')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('from_date')) {
            $query->where('transaction_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        $expenses = $query->paginate($request->input('per_page', 15));

        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:USD,KHR',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
            'reference_no' => 'nullable|string',
            'payment_method' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = $request->user()?->id;

        $expense = Expense::create($data);
        $expense->load('expenseCategory');

        return response()->json([
            'message' => 'Expense recorded successfully',
            'data' => $expense
        ], 201);
    }

    public function destroy(int $id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return response()->json(['message' => 'Expense deleted successfully']);
    }
}
