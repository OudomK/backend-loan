<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowingRepayment extends Model
{
    protected $fillable = [
        'borrowing_id',
        'payment_date',
        'principal_paid',
        'interest_paid',
        'total_paid',
        'payment_method',
        'remarks'
    ];

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }
}
