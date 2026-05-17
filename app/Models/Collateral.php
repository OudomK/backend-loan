<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collateral extends Model
{
    protected $fillable = [
        'loan_id',
        'type',
        'certificate_number',
        'license_plate',
        'owner_name',
        'value',
        'currency',
        'start_date',
        'end_date',
        'status',
        'description'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'value' => 'double',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
