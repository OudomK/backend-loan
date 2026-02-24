<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index()
    {
        return response()->json(LeaveRequest::with('employee')->latest()->get());
    }

    public function show($id)
    {
        return response()->json(LeaveRequest::with(['employee', 'approver'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type' => 'required|string|in:Annual,Sick,Special,Maternity,Paternity,Unpaid,Other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $leaveRequest = LeaveRequest::create($validated);
        return response()->json($leaveRequest, 201);
    }

    public function update(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:Pending,Approved,Rejected',
            'approved_by' => 'nullable|exists:users,id',
            'rejection_reason' => 'nullable|string',
            'leave_type' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $leaveRequest->update($validated);
        return response()->json($leaveRequest);
    }

    public function destroy($id)
    {
        LeaveRequest::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
