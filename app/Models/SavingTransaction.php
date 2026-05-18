<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class SavingTransaction extends Model
{
    use Auditable;

    protected $fillable = [
        'saving_account_id',
        'transaction_type',
        'amount',
        'currency',
        'transaction_date',
        'reference_no',
        'description',
        'balance_after',
        'performed_by',
    ];

    public function account()
    {
        return $this->belongsTo(SavingAccount::class, 'saving_account_id');
    }
}
