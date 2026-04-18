<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_kh' => $this->name_kh,
            'employee_code' => $this->employee_code,
            'gender' => $this->gender,
            'photo' => $this->photo,
            'dob' => $this->dob,
            'id_card_number' => $this->id_card_number,
            'marital_status' => $this->marital_status,
            'number_of_children' => $this->number_of_children,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'position_id' => $this->position_id,
            'employment_type' => $this->employment_type,
            'contract_end_date' => $this->contract_end_date,
            'working_days_per_week' => $this->working_days_per_week,
            'salary' => $this->salary,
            'currency' => $this->currency,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'nssf_id' => $this->nssf_id,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'date_joined' => $this->date_joined,
            'status' => $this->status,
            'position' => $this->whenLoaded('position'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
