<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapitalShare extends Model
{

    protected $fillable = [
        'lender_id', // Investor
        'account_no',
        'category', // Share Type (Regular, Premium)
        'share_qty',
        'par_value',
        'total_capital',
        'currency',
        'status',
        // Legacy fields for backward compat, but key focus is above
        'borrower_id',
        'transaction_no',
        'loan_account',
        'borrowing_date',
        'contract_no',
        'payment_method',
        'first_pay_date',
        'term_months',
        'amount',
        'interest_rate',
        'int_pay_mode',
        'fee',
        'maturity_date',
        'sl_term',
        'dividends',
        'balance',
        'holder_id',
        'certificate_no',
        'purchase_date',
        'repayment_schedule',
    ];

    protected $casts = [
        'repayment_schedule' => 'array',
    ];

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CapitalShareTransaction::class);
    }
}
