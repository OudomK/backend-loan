<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MiscellaneousTransaction extends Model
{

    protected $fillable = [
        'type',
        'category',
        'amount',
        'currency',
        'transaction_date',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];
}
