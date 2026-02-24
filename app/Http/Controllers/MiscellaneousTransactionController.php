<?php

namespace App\Http\Controllers;

use App\Models\MiscellaneousTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MiscellaneousTransactionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = MiscellaneousTransaction::orderBy('transaction_date', 'desc');

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
            'type' => 'required|in:revenue,expense',
            'category' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'required|string',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        try {
            $transaction = MiscellaneousTransaction::create($request->all());
            return response()->json([
                'success' => true,
                'data' => $transaction,
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
