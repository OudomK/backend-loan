<?php

namespace App\Http\Controllers;

use App\Models\LoanOfficer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanOfficerController extends Controller
{
    public function index()
    {
        return response()->json(LoanOfficer::with('employee')->withCount(['loans as total_active_loans' => function($query) {
            $query->where('status', 'active');
        }])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'phone_2' => 'nullable|string',
            'phone_3' => 'nullable|string',
            'status' => 'required|in:active,inactive,Active,Inactive',
            'employee_id' => 'nullable|integer|exists:employees,id|unique:loan_officers,employee_id',
            'start_date' => 'nullable|date',
            'max_loan_amount' => 'nullable|numeric',
            'gender' => 'nullable|string',
        ]);

        $validated['status'] = strtolower($validated['status']);
        $officer = LoanOfficer::create($validated);
        return response()->json($officer, 201);
    }

    public function show(LoanOfficer $loanOfficer)
    {
        return response()->json($loanOfficer);
    }

    public function update(Request $request, LoanOfficer $loanOfficer)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'phone' => 'nullable|string',
            'phone_2' => 'nullable|string',
            'phone_3' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive,Active,Inactive',
            'employee_id' => ['nullable', 'integer', 'exists:employees,id', Rule::unique('loan_officers', 'employee_id')->ignore($loanOfficer->id)],
            'start_date' => 'nullable|date',
            'max_loan_amount' => 'nullable|numeric',
            'gender' => 'nullable|string',
        ]);

        if (array_key_exists('status', $validated)) {
            $validated['status'] = strtolower($validated['status']);
        }
        $loanOfficer->update($validated);
        return response()->json($loanOfficer);
    }

    public function destroy(LoanOfficer $loanOfficer)
    {
        $loanOfficer->delete();
        return response()->json(null, 204);
    }
}
