<?php

namespace App\Http\Controllers;

use App\Models\LoanOfficer;
use Illuminate\Http\Request;

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
            'status' => 'required|in:active,inactive',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'start_date' => 'nullable|date',
            'max_loan_amount' => 'nullable|numeric',
            'gender' => 'nullable|string',
        ]);

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
            'status' => 'sometimes|required|in:active,inactive',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'start_date' => 'nullable|date',
            'max_loan_amount' => 'nullable|numeric',
            'gender' => 'nullable|string',
        ]);

        $loanOfficer->update($validated);
        return response()->json($loanOfficer);
    }

    public function destroy(LoanOfficer $loanOfficer)
    {
        $loanOfficer->delete();
        return response()->json(null, 204);
    }
}
