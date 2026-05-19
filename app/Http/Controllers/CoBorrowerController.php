<?php

namespace App\Http\Controllers;

use App\Models\CoBorrower;
use App\Models\Loan;
use Illuminate\Http\Request;

class CoBorrowerController extends Controller
{
    public function index(Request $request)
    {
        $query = CoBorrower::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('id_number', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('age', 'like', $like)
                    ->orWhere('customer_code', 'like', $like)
                    ->orWhereHas('loans', function ($q) use ($search) {
                        $q->where('loan_code', 'like', "%{$search}%");
                    });
            });

            $results = $query
                ->select([
                    'id',
                    'first_name',
                    'last_name',
                    'phone',
                    'id_number',
                    'customer_code',
                ])
                ->addSelect([
                    'latest_loan_code' => Loan::query()
                        ->select('loan_code')
                        ->whereColumn('loans.co_borrower_id', 'co_borrowers.id')
                        ->whereNull('loans.deleted_at')
                        ->latest('id')
                        ->limit(1),
                ])
                ->orderByRaw(
                    "CASE
                        WHEN customer_code LIKE ? THEN 0
                        WHEN phone LIKE ? THEN 1
                        WHEN id_number LIKE ? THEN 2
                        WHEN first_name LIKE ? THEN 3
                        WHEN last_name LIKE ? THEN 4
                        ELSE 5
                    END",
                    ["{$search}%", "{$search}%", "{$search}%", "{$search}%", "{$search}%"]
                )
                ->limit(15)
                ->get()
                ->map(function (CoBorrower $coBorrower) {
                    return [
                        'id' => (string) $coBorrower->id,
                        'first_name' => (string) ($coBorrower->first_name ?? ''),
                        'last_name' => (string) ($coBorrower->last_name ?? ''),
                        'name' => trim(($coBorrower->first_name ?? '') . ' ' . ($coBorrower->last_name ?? '')),
                        'phone' => (string) ($coBorrower->phone ?? ''),
                        'id_number' => (string) ($coBorrower->id_number ?? ''),
                        'customer_code' => (string) ($coBorrower->customer_code ?? ''),
                        'code' => (string) ($coBorrower->customer_code ?? ''),
                        'latest_loan_code' => (string) ($coBorrower->latest_loan_code ?? ''),
                    ];
                })
                ->values();

            return response()->json($results);
        }

        // Laravel SoftDeletes will automatically filter out deleted records.

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
            'customer_code' => 'nullable|string|unique:co_borrowers',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'nullable|string|max:20',
            'id_type' => 'nullable|string',
            'id_number' => 'nullable|string|unique:co_borrowers',
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
        ]);

        if (empty($validated['customer_code'])) {
            $latest = CoBorrower::withTrashed()->orderBy('id', 'desc')->first();
            $nextId = $latest ? $latest->id + 1 : 1;
            $validated['customer_code'] = 'CB-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        }

        $coBorrower = CoBorrower::create($validated);
        return response()->json($coBorrower, 201);
    }

    public function show(CoBorrower $coBorrower)
    {
        return response()->json($coBorrower);
    }

    public function update(Request $request, CoBorrower $coBorrower)
    {
        $validated = $request->validate([
            'customer_code' => 'sometimes|nullable|string|unique:co_borrowers,customer_code,' . $coBorrower->id,
            'first_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'sometimes|nullable|string|max:20',
            'id_type' => 'nullable|string',
            'id_number' => 'sometimes|nullable|string|unique:co_borrowers,id_number,' . $coBorrower->id,
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
        ]);

        $coBorrower->update($validated);
        return response()->json($coBorrower);
    }

    public function destroy(CoBorrower $coBorrower)
    {
        $coBorrower->delete();
        return response()->json(null, 204);
    }

    public function getNextCode()
    {
        $latest = CoBorrower::withTrashed()->orderBy('id', 'desc')->first();
        $nextId = $latest ? $latest->id + 1 : 1;
        $code = 'CB-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        return response()->json(['code' => $code]);
    }
}
