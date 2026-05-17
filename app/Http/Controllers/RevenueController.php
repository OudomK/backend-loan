<?php

namespace App\Http\Controllers;

use App\Models\Revenue;
use Illuminate\Http\Request;

class RevenueController extends Controller
{
    public function index()
    {
        return Revenue::with('revenue_category')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'revenue_category_id' => 'required|exists:revenue_categories,id',
            'amount' => 'required|numeric',
            'currency' => 'required|string|max:10',
            'transaction_date' => 'required|date',
            'reference_no' => 'nullable|string|unique:revenues,reference_no',
            'payment_method' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        return Revenue::create($validated)->load('revenue_category');
    }

    public function show(Revenue $revenue)
    {
        return $revenue->load('revenue_category');
    }

    public function update(Request $request, Revenue $revenue)
    {
        $validated = $request->validate([
            'revenue_category_id' => 'exists:revenue_categories,id',
            'amount' => 'numeric',
            'currency' => 'string|max:10',
            'transaction_date' => 'date',
            'reference_no' => 'nullable|string|unique:revenues,reference_no,' . $revenue->id,
            'payment_method' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $revenue->update($validated);
        return $revenue->load('revenue_category');
    }

    public function destroy(Revenue $revenue)
    {
        $revenue->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
