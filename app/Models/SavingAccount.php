<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingAccount extends Model
{
    protected $fillable = [
        'borrower_id',
        'account_number',
        'account_type',
        'currency',
        'interest_rate',
        'balance',
        'term',
        'maturity_date',
        'status',
    ];

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }

    public function transactions()
    {
        return $this->hasMany(SavingTransaction::class);
    }
}
