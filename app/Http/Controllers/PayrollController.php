<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index()
    {
        return response()->json(\App\Models\Payroll::with('employee')->get());
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month_year' => 'required|date',
            'salary' => 'required|numeric',
            'allowance' => 'nullable|numeric',
            'bonus' => 'nullable|numeric',
            'deduction' => 'nullable|numeric',
            'total_payable' => 'required|numeric',
            'status' => 'required|string',
            'payment_date' => 'nullable|date',
        ]);

        $payroll = \App\Models\Payroll::create($validated);
        return response()->json($payroll, 201);
    }

    public function update(\Illuminate\Http\Request $request, $id)
    {
        $payroll = \App\Models\Payroll::findOrFail($id);
        $validated = $request->validate([
            'employee_id' => 'sometimes|exists:employees,id',
            'month_year' => 'sometimes|date',
            'salary' => 'sometimes|numeric',
            'allowance' => 'nullable|numeric',
            'bonus' => 'nullable|numeric',
            'deduction' => 'nullable|numeric',
            'total_payable' => 'sometimes|numeric',
            'status' => 'sometimes|string',
            'payment_date' => 'nullable|date',
        ]);

        $payroll->update($validated);
        return response()->json($payroll);
    }

    public function destroy($id)
    {
        \App\Models\Payroll::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
