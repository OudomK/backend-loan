<?php

namespace App\Http\Controllers;

use App\Models\Guarantor;
use Illuminate\Http\Request;

class GuarantorController extends Controller
{
    public function index(Request $request)
    {
        $query = Guarantor::query();

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('age', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        $query->where('status', '!=', 'Deleted');

        if ($request->has('id_number')) {
            $query->where('id_number', $request->query('id_number'));
        }

        if ($request->has('phone')) {
            $query->where('phone', $request->query('phone'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_code' => 'nullable|string|unique:guarantors',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'nullable|string|max:20',
            'id_type' => 'nullable|string',
            'id_number' => 'nullable|string|unique:guarantors',
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
        ]);

        if (empty($validated['customer_code'])) {
            $latest = \App\Models\Guarantor::orderBy('id', 'desc')->first();
            $nextId = $latest ? $latest->id + 1 : 1;
            $validated['customer_code'] = 'GU-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        }

        $guarantor = \App\Models\Guarantor::create($validated);
        return response()->json($guarantor, 201);
    }

    public function show(Guarantor $guarantor)
    {
        return response()->json($guarantor);
    }

    public function update(Request $request, \App\Models\Guarantor $guarantor)
    {
        $validated = $request->validate([
            'customer_code' => 'sometimes|nullable|string|unique:guarantors,customer_code,' . $guarantor->id,
            'first_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'sometimes|nullable|string|max:20',
            'id_type' => 'nullable|string',
            'id_number' => 'sometimes|nullable|string|unique:guarantors,id_number,' . $guarantor->id,
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
        ]);

        $guarantor->update($validated);
        return response()->json($guarantor);
    }

    public function destroy(Guarantor $guarantor)
    {
        $guarantor->update(['status' => 'Deleted']);
        return response()->json(null, 204);
    }

    public function getNextCode()
    {
        $latest = \App\Models\Guarantor::orderBy('id', 'desc')->first();
        $nextId = $latest ? $latest->id + 1 : 1;
        $code = 'GU-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        return response()->json(['code' => $code]);
    }
}
