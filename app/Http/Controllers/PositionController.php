<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function index()
    {
        $positions = Position::with(['reportingTo'])
            ->withCount('employees')
            ->get()
            ->map(function (Position $p) {
                $arr = $p->toArray();
                $arr['current_headcount'] = $p->employees_count;
                return $arr;
            });
        return response()->json($positions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|unique:positions',
            'name' => 'required|string',
            'department' => 'nullable|string',
            'type' => 'required|string',
            'base_salary' => 'required|numeric',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'status' => 'required|string',
            'reporting_to_id' => 'nullable|exists:positions,id',
            'min_headcount' => 'nullable|integer|min:0',
            'max_headcount' => 'nullable|integer|min:0',
        ]);

        $position = Position::create($validated);
        $position->load('reportingTo');
        $arr = $position->toArray();
        $arr['current_headcount'] = 0;
        return response()->json($arr, 201);
    }

    public function update(Request $request, $id)
    {
        $position = Position::findOrFail($id);
        $validated = $request->validate([
            'code' => 'nullable|string|unique:positions,code,' . $id,
            'name' => 'sometimes|string',
            'department' => 'nullable|string',
            'type' => 'sometimes|string',
            'base_salary' => 'sometimes|numeric',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'status' => 'sometimes|string',
            'reporting_to_id' => 'nullable|exists:positions,id',
            'min_headcount' => 'nullable|integer|min:0',
            'max_headcount' => 'nullable|integer|min:0',
        ]);

        $position->update($validated);
        $position->load('reportingTo');
        $arr = $position->toArray();
        $arr['current_headcount'] = $position->employees()->count();
        return response()->json($arr);
    }

    public function destroy($id)
    {
        Position::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
