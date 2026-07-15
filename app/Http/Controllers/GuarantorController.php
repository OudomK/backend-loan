<?php

namespace App\Http\Controllers;

use App\Models\Guarantor;
use App\Models\Loan;
use Illuminate\Http\Request;

class GuarantorController extends Controller
{
    public function index(Request $request)
    {
        $query = Guarantor::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('latin_name', 'like', $like)
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
                    'latin_name',
                    'phone',
                    'id_number',
                    'customer_code',
                ])
                ->addSelect([
                    'latest_loan_code' => Loan::query()
                        ->select('loan_code')
                        ->whereColumn('loans.guarantor_id', 'guarantors.id')
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
                ->map(function (Guarantor $guarantor) {
                    return [
                        'id' => (string) $guarantor->id,
                        'first_name' => (string) ($guarantor->first_name ?? ''),
                        'last_name' => (string) ($guarantor->last_name ?? ''),
                        'latin_name' => (string) ($guarantor->latin_name ?? ''),
                        'name' => trim(($guarantor->first_name ?? '') . ' ' . ($guarantor->last_name ?? '')),
                        'phone' => (string) ($guarantor->phone ?? ''),
                        'id_number' => (string) ($guarantor->id_number ?? ''),
                        'customer_code' => (string) ($guarantor->customer_code ?? ''),
                        'code' => (string) ($guarantor->customer_code ?? ''),
                        'latest_loan_code' => (string) ($guarantor->latest_loan_code ?? ''),
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
            'customer_code' => 'nullable|string|unique:guarantors',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'latin_name' => 'nullable|string|max:255',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'nullable|string|max:60',
            'id_type' => 'nullable|string',
            'id_number' => 'nullable|string|unique:guarantors',
            'id_issue_date' => 'nullable|date_format:d/m/Y',
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
            'row_no' => 'nullable|integer',
        ]);

        if (empty($validated['customer_code'])) {
            $latest = Guarantor::withTrashed()->orderBy('id', 'desc')->first();
            $nextId = $latest ? $latest->id + 1 : 1;
            $validated['customer_code'] = 'GU-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        }

        $guarantor = Guarantor::create($validated);
        return response()->json($guarantor, 201);
    }

    public function show(Guarantor $guarantor)
    {
        return response()->json($guarantor);
    }

    public function update(Request $request, Guarantor $guarantor)
    {
        $validated = $request->validate([
            'customer_code' => 'sometimes|nullable|string|unique:guarantors,customer_code,' . $guarantor->id,
            'first_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'latin_name' => 'sometimes|nullable|string|max:255',
            'gender' => 'nullable|string',
            'marital_status' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'sometimes|nullable|string|max:60',
            'id_type' => 'nullable|string',
            'id_number' => 'sometimes|nullable|string|unique:guarantors,id_number,' . $guarantor->id,
            'id_issue_date' => 'nullable|date_format:d/m/Y',
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
            'row_no' => 'nullable|integer',
        ]);

        $guarantor->update($validated);
        return response()->json($guarantor);
    }

    public function destroy(Guarantor $guarantor)
    {
        $guarantor->delete();
        return response()->json(null, 204);
    }

    public function getNextCode()
    {
        $latest = Guarantor::withTrashed()->orderBy('id', 'desc')->first();
        $nextId = $latest ? $latest->id + 1 : 1;
        $code = 'GU-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        return response()->json(['code' => $code]);
    }
}
