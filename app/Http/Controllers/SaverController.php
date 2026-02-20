<?php

namespace App\Http\Controllers;

use App\Models\Saver;
use Illuminate\Http\Request;

class SaverController extends Controller
{
    public function index(Request $request)
    {
        $query = Saver::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_code' => 'nullable|string|unique:savers',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'dob' => 'nullable|string',
            'age' => 'nullable|integer',
            'phone' => 'required|string',
            'id_type' => 'nullable|string',
            'id_number' => 'required|string|unique:savers',
            'id_expiry' => 'nullable|string',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
            'status' => 'nullable|string',
            'customer_type' => 'nullable|string'
        ]);

        if (!isset($validated['customer_code'])) {
            $lastCode = Saver::orderBy('id', 'desc')->first();
            $nextNumber = $lastCode ? intval(substr($lastCode->customer_code, 3)) + 1 : 1;
            $validated['customer_code'] = 'SAV' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        $saver = Saver::create($validated);
        return response()->json($saver, 201);
    }

    public function show($id)
    {
        $saver = Saver::findOrFail($id);
        return response()->json($saver);
    }

    public function update(Request $request, $id)
    {
        $saver = Saver::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string',
            'last_name' => 'sometimes|required|string',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'dob' => 'nullable|string',
            'age' => 'nullable|integer',
            'phone' => 'sometimes|required|string',
            'id_type' => 'nullable|string',
            'id_number' => 'sometimes|required|string|unique:savers,id_number,' . $id,
            'id_expiry' => 'nullable|string',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
            'status' => 'nullable|string'
        ]);

        $saver->update($validated);
        return response()->json($saver);
    }

    public function destroy($id)
    {
        $saver = Saver::findOrFail($id);
        $saver->delete();
        return response()->json(null, 204);
    }

    public function getNextCode()
    {
        $lastCode = Saver::orderBy('id', 'desc')->first();
        $nextNumber = $lastCode ? intval(substr($lastCode->customer_code, 3)) + 1 : 1;
        $code = 'SAV' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        return response()->json(['code' => $code]);
    }
}
