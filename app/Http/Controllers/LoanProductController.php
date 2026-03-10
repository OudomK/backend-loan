<?php

namespace App\Http\Controllers;

use App\Models\LoanProduct;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    public function index()
    {
        return response()->json(LoanProduct::where('is_active', true)->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:loan_products,code',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $product = LoanProduct::create($validated);

        return response()->json($product, 201);
    }

    public function show(LoanProduct $loanProduct)
    {
        return response()->json($loanProduct);
    }

    public function update(Request $request, LoanProduct $loanProduct)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|unique:loan_products,code,' . $loanProduct->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $loanProduct->update($validated);

        return response()->json($loanProduct);
    }

    public function destroy(LoanProduct $loanProduct)
    {
        $loanProduct->delete();
        return response()->json(null, 204);
    }
}
