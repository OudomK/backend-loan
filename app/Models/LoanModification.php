<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanModification extends Model
{
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
