<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DividendSchedule extends Model
{
    protected $fillable = [
        'currency',
        'type',
        'amount',
        'frequency',
        'day_of_month',
        'is_active',
        'last_run_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'amount' => 'decimal:4',
        'day_of_month' => 'integer',
        'last_run_at' => 'datetime',
    ];
}
