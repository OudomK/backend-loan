<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LoanModification extends Model
{
    use Auditable;

    protected $fillable = [
        'loan_id',
        'type',
        'old_data',
        'new_data',
        'notes',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
