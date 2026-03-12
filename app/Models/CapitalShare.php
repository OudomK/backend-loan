<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapitalShare extends Model
{

    protected $fillable = [
        'lender_id', // Investor
        'investor_id',
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
        'dividends',
        'total_dividend_paid',
        'last_dividend_date',
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

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'investor_id');
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
