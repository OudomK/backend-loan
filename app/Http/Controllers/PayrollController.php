<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index()
    {
        return response()->json(\App\Models\Payroll::with('employee.position')->orderByDesc('month_year')->get());
    }

    public function show($id)
    {
        return response()->json(\App\Models\Payroll::with('employee.position')->findOrFail($id));
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
            'status' => 'required|string',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string',
        ]);

        $validated['total_payable'] = ($validated['salary'] ?? 0)
            + ($validated['allowance'] ?? 0)
            + ($validated['bonus'] ?? 0)
            - ($validated['deduction'] ?? 0);

        $payroll = \App\Models\Payroll::create($validated);
        return response()->json($payroll->load('employee.position'), 201);
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
            'status' => 'sometimes|string',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string',
        ]);

        $salary = $validated['salary'] ?? $payroll->salary;
        $allowance = $validated['allowance'] ?? $payroll->allowance;
        $bonus = $validated['bonus'] ?? $payroll->bonus;
        $deduction = $validated['deduction'] ?? $payroll->deduction;
        $validated['total_payable'] = $salary + $allowance + $bonus - $deduction;

        $payroll->update($validated);
        return response()->json($payroll->load('employee.position'));
    }

    public function destroy($id)
    {
        \App\Models\Payroll::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
