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
            'gender' => 'nullable|string|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'position_id' => 'required|exists:positions,id',
            'salary' => 'required|numeric|min:0',
            'date_joined' => 'nullable|date',
            'status' => 'required|string|in:active,inactive',
        ];
    }
}
