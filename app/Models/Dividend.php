<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dividend extends Model
{
    protected $fillable = [
        'total_amount',
        'dividend_per_share',
        'currency',
        'total_shares_count',
        'declared_date',
        'status',
    ];

    public function transactions()
    {
        return $this->hasMany(DividendTransaction::class);
    }
}
