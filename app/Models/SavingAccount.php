<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingAccount extends Model
{
    protected $fillable = [
        'borrower_id',
        'lender_id',
        'transaction_no',
        'loan_account',
        'category',
        'borrowing_date',
        'account_number',
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
        'balance',
        'status',
        'late_principal',
        'loan_interest',
        'account_type',
        'term',
    ];

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingTransaction::class);
    }
}
