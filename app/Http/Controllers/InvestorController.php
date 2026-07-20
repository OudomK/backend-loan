<?php

namespace App\Http\Controllers;

use App\Models\Investor;
use Illuminate\Http\Request;

class InvestorController extends Controller
{
    public function index(Request $request)
    {
        $query = Investor::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('latin_name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_code' => 'nullable|string|unique:investors',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'latin_name' => 'nullable|string',
            'nickname' => 'nullable|string',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'dob' => 'nullable|string',
            'age' => 'nullable|integer',
            'phone' => 'required|string',
            'id_type' => 'nullable|string',
            'id_number' => 'required|string|unique:investors',
            'id_issue_date' => 'nullable|string',
            'id_expiry' => 'nullable|string',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
            'status' => 'nullable|string',
            'customer_type' => 'nullable|string',
            'row_no' => 'nullable|integer'
        ]);

        // Remove null status so MySQL uses DEFAULT 'Active'
        if (array_key_exists('status', $validated) && empty($validated['status'])) {
            unset($validated['status']);
        }

        if (!isset($validated['customer_code'])) {
            $lastCode = Investor::orderBy('id', 'desc')->first();
            $nextNumber = $lastCode ? intval(substr($lastCode->customer_code, 3)) + 1 : 1;
            $validated['customer_code'] = 'INV' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        $investor = Investor::create($validated);
        return response()->json($investor, 201);
    }

    public function show(string $id)
    {
        $investor = Investor::findOrFail($id);
        return response()->json($investor);
    }

    public function update(Request $request, string $id)
    {
        $investor = Investor::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string',
            'last_name' => 'sometimes|required|string',
            'latin_name' => 'sometimes|nullable|string',
            'nickname' => 'sometimes|nullable|string',
            'gender' => 'sometimes|nullable|string',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'dob' => 'nullable|string',
            'age' => 'nullable|integer',
            'phone' => 'sometimes|required|string',
            'id_type' => 'nullable|string',
            'id_number' => 'sometimes|required|string|unique:investors,id_number,' . $id,
            'id_issue_date' => 'nullable|string',
            'id_expiry' => 'nullable|string',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
            'status' => 'nullable|string',
            'row_no' => 'nullable|integer',
        ]);

        $investor->update($validated);
        return response()->json($investor);
    }

    public function destroy(string $id)
    {
        $investor = Investor::findOrFail($id);
        $investor->delete();
        return response()->json(null, 204);
    }

    public function getNextCode()
    {
        $lastCode = Investor::orderBy('id', 'desc')->first();
        $nextNumber = $lastCode ? intval(substr($lastCode->customer_code, 3)) + 1 : 1;
        $code = 'INV' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        return response()->json(['code' => $code]);
    }
}
