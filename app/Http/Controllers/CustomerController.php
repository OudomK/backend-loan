<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('age', 'like', "%{$search}%");
            });
        }

        // Always filter out deleted customers
        $query->where('status', '!=', 'Deleted');

        if ($request->has('role')) {
            $role = $request->query('role');
            if ($role === 'co_borrower') {
                $query->whereHas('coBorrowerLoans');
            } elseif ($role === 'guarantor') {
                $query->whereHas('guarantorLoans');
            }
            // If role is 'borrower' or anything else, we don't apply strict whereHas('loans') 
            // to avoid hiding customers who don't have a loan record yet.
        }

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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'required|string|max:20',
            'id_type' => 'nullable|string',
            'id_number' => 'required|string|unique:customers',
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
        ]);

        $customer = Customer::create($validated);
        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'gender' => 'nullable|string',
            'age' => 'nullable|integer',
            'dob' => 'nullable|date_format:d/m/Y',
            'phone' => 'sometimes|required|string|max:20',
            'id_type' => 'nullable|string',
            'id_number' => 'sometimes|required|string|unique:customers,id_number,' . $customer->id,
            'id_expiry' => 'nullable|date_format:d/m/Y',
            'occupation' => 'nullable|string',
            'village' => 'nullable|string',
            'commune' => 'nullable|string',
            'district' => 'nullable|string',
            'province' => 'nullable|string',
            'photo' => 'nullable|string',
        ]);

        $customer->update($validated);
        return response()->json($customer);
    }

    public function destroy(Customer $customer)
    {
        $customer->update(['status' => 'Deleted']);
        return response()->json(null, 204);
    }
}
