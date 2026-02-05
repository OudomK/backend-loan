<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Borrowing extends Model
{
    protected $fillable = [
        'lender_id',
        'transaction_no',
        'loan_account',
        'category',
        'borrowing_date',
        'account_no',
        'contract_no',
        'payment_method',
        'first_pay_date',
        'currency',
        'term_months',
        'amount',
        'interest_rate',
        'int_pay_mode',
        'fee',
        'maturity_date',
        'sl_term',
        'status',
        'late_principal',
        'loan_interest',
    ];

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(BorrowingRepayment::class);
    }
}
