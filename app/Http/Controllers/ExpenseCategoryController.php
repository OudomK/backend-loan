<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpenseCategory::query()->orderBy('sort_order')->orderBy('name');

        // Only show active categories by default unless explicitly asked for all
        if ($request->has('active_only')) {
            if ($request->boolean('active_only')) {
                $query->where('is_active', true);
            }
        } else {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name',
            'group_name' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $category = ExpenseCategory::create([
            'name' => trim((string) $validated['name']),
            'group_name' => blank($validated['group_name'] ?? null) ? null : trim((string) $validated['group_name']),
            'description' => blank($validated['description'] ?? null) ? null : trim((string) $validated['description']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return response()->json([
            'success' => true,
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $category = ExpenseCategory::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:expense_categories,name,' . $id,
            'group_name' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim((string) $validated['name']);
        }

        if (array_key_exists('group_name', $validated)) {
            $validated['group_name'] = blank($validated['group_name']) ? null : trim((string) $validated['group_name']);
        }

        if (array_key_exists('description', $validated)) {
            $validated['description'] = blank($validated['description']) ? null : trim((string) $validated['description']);
        }

        $category->update($validated);

        return response()->json([
            'success' => true,
            'data' => $category->fresh(),
        ]);
    }

    public function destroy(int $id)
    {
        $category = ExpenseCategory::query()->findOrFail($id);

        if ($category->miscellaneousTransactions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This category is already used by miscellaneous transactions and cannot be deleted.',
            ], 422);
        }

        $category->delete();

        return response()->json(['success' => true]);
    }
}
