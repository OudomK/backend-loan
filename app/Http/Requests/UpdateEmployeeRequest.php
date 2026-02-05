<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'position_id' => 'sometimes|required|exists:positions,id',
            'salary' => 'sometimes|required|numeric|min:0',
            'date_joined' => 'nullable|date',
            'status' => 'sometimes|required|string|in:active,inactive',
        ];
    }
}
