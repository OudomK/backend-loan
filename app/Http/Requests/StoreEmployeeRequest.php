<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'name_kh' => 'nullable|string|max:255',
            'employee_code' => 'nullable|string|unique:employees,employee_code|max:50',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'photo' => 'nullable|string',
            'dob' => 'nullable|date',
            'id_card_number' => 'nullable|string|max:50',
            'marital_status' => 'nullable|string|max:50',
            'number_of_children' => 'nullable|integer|min:0',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'position_id' => 'required|exists:positions,id',
            'employment_type' => 'nullable|string|max:50',
            'contract_end_date' => 'nullable|date',
            'working_days_per_week' => 'nullable|integer|min:1|max:7',
            'salary' => 'required|numeric|min:0',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'nssf_id' => 'nullable|string|max:50',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'date_joined' => 'nullable|date',
            'status' => 'required|string|in:active,inactive',
        ];
    }
}
