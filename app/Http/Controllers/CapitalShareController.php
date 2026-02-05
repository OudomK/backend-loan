<?php

namespace App\Http\Controllers;

use App\Models\CapitalShare;
use Illuminate\Http\Request;

class CapitalShareController extends Controller
{
    public function index()
    {
        return response()->json(CapitalShare::with('borrower')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'borrower_id' => 'required|exists:borrowers,id',
            'holder_id' => 'required|unique:capital_shares',
            'certificate_no' => 'required|unique:capital_shares',
            'share_qty' => 'required|integer|min:1',
            'par_value' => 'required|numeric',
            'total_capital' => 'required|numeric',
            'purchase_date' => 'required|date',
            'status' => 'required|in:Active,Withdrawn',
        ]);

        $share = CapitalShare::create($validated);
        return response()->json($share->load('borrower'), 201);
    }

    public function update(Request $request, CapitalShare $share)
    {
        $validated = $request->validate([
            'share_qty' => 'sometimes|integer|min:1',
            'total_capital' => 'sometimes|numeric',
            'status' => 'sometimes|in:Active,Withdrawn',
        ]);

        $share->update($validated);
        return response()->json($share);
    }
}
