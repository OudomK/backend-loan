<?php

namespace App\Http\Controllers;

use App\Models\RevenueCategory;
use Illuminate\Http\Request;

class RevenueCategoryController extends Controller
{
    public function index(Request $request)
    {
        $activeOnly = $request->get('active_only', true);
        
        $query = RevenueCategory::query();
        
        if ($activeOnly === true || $activeOnly === 'true' || $activeOnly === '1') {
            $query->where('is_active', true);
        }
        
        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        return RevenueCategory::create($validated);
    }

    public function show(RevenueCategory $revenueCategory)
    {
        return $revenueCategory;
    }

    public function update(Request $request, RevenueCategory $revenueCategory)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'group_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $revenueCategory->update($validated);
        return $revenueCategory;
    }

    public function destroy(RevenueCategory $revenueCategory)
    {
        $revenueCategory->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
