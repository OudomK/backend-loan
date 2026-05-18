<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowingSchedule extends Model
{
    use Auditable;

    protected $fillable = [
        'borrowing_id',
        'installment_no',
        'due_date',
        'principal_due',
        'interest_due',
        'total_due',
        'principal_paid',
        'interest_paid',
        'penalty_paid',
        'status',
        'paid_date',
        'last_payment_date',
        'note'
    ];

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }
}
