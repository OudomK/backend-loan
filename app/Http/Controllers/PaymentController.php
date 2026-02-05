<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Payment::with('loan.customer')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'payment_number' => 'required|integer',
            'principal_amount' => 'required|numeric',
            'interest_amount' => 'required|numeric',
            'penalty_amount' => 'nullable|numeric',
            'total_paid' => 'required|numeric',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
        ]);

        $payment = Payment::create($validated);
        return response()->json($payment, 201);
    }

    public function show(Payment $payment)
    {
        return response()->json($payment->load('loan.customer'));
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'payment_number' => 'sometimes|required|integer',
            'principal_amount' => 'sometimes|required|numeric',
            'interest_amount' => 'sometimes|required|numeric',
            'penalty_amount' => 'nullable|numeric',
            'total_paid' => 'sometimes|required|numeric',
            'payment_date' => 'sometimes|required|date',
            'payment_method' => 'sometimes|required|string',
        ]);

        $payment->update($validated);
        return response()->json($payment);
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return response()->json(null, 204);
    }
}
