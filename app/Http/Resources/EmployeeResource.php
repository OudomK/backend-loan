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
            'gender' => $this->gender,
            'dob' => $this->dob,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'position_id' => $this->position_id,
            'salary' => $this->salary,
            'date_joined' => $this->date_joined,
            'status' => $this->status,
            'position' => $this->whenLoaded('position'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
