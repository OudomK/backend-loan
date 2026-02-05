<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingTransaction extends Model
{
    protected $fillable = [
        'saving_account_id',
        'transaction_type',
        'amount',
        'currency',
        'transaction_date',
        'reference_no',
        'description',
    ];

    public function account()
    {
        return $this->belongsTo(SavingAccount::class, 'saving_account_id');
    }
}
