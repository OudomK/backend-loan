<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use Illuminate\Http\Request;

class BorrowerController extends Controller
{
    public function index(Request $request)
    {
        $query = Borrower::query();

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhereHas('loans', function ($q) use ($search) {
                        $q->where('loan_code', 'like', "%{$search}%");
                    });
            });
            $query->limit(25);
        }

        if ($request->has('customer_type')) {
            $query->where('customer_type', $request->query('customer_type'));
        }

        $query->where('status', '!=', 'Deleted');

        if ($request->has('id_number')) {
            $query->where('id_number', $request->query('id_number'));
        }

        if ($request->has('phone')) {
            $query->where('phone', $request->query('phone'));
        }

        return response()->json($query->with('loans')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_code' => 'nullable|string|unique:borrowers',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'nullable|string|max:20',
            'id_type' => 'nullable|string',
            'id_number' => 'nullable|string|unique:borrowers',
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
            'customer_type' => 'nullable|string|in:Borrower,Saver,Investor',
        ]);

        if (empty($validated['customer_code'])) {
            $latest = Borrower::orderBy('id', 'desc')->first();
            $nextId = $latest ? $latest->id + 1 : 1;
            $validated['customer_code'] = 'QF-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        }

        $borrower = Borrower::create($validated);
        return response()->json($borrower, 201);
    }

    public function show(Borrower $borrower)
    {
        return response()->json($borrower);
    }

    public function update(Request $request, Borrower $borrower)
    {
        $validated = $request->validate([
            'customer_code' => 'sometimes|nullable|string|unique:borrowers,customer_code,' . $borrower->id,
            'first_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'sometimes|nullable|string|max:20',
            'id_type' => 'nullable|string',
            'id_number' => 'sometimes|nullable|string|unique:borrowers,id_number,' . $borrower->id,
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
            'customer_type' => 'sometimes|nullable|string|in:Borrower,Saver,Investor',
        ]);

        $borrower->update($validated);
        return response()->json($borrower);
    }

    public function destroy(Borrower $borrower)
    {
        $borrower->update(['status' => 'Deleted']);
        return response()->json(null, 204);
    }

    public function getNextCode()
    {
        $latest = Borrower::orderBy('id', 'desc')->first();
        $nextId = $latest ? $latest->id + 1 : 1;
        $code = 'QF-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        return response()->json(['code' => $code]);
    }
}
