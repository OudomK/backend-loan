<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function index()
    {
        return response()->json(\App\Models\Position::all());
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'department' => 'nullable|string',
            'base_salary' => 'required|numeric',
            'status' => 'required|string',
        ]);

        $position = \App\Models\Position::create($validated);
        return response()->json($position, 201);
    }

    public function update(\Illuminate\Http\Request $request, $id)
    {
        $position = \App\Models\Position::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'department' => 'nullable|string',
            'base_salary' => 'sometimes|numeric',
            'status' => 'sometimes|string',
        ]);

        $position->update($validated);
        return response()->json($position);
    }

    public function destroy($id)
    {
        \App\Models\Position::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
