<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dividend extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'total_amount',
        'dividend_per_share',
        'currency',
        'distribution_basis',
        'total_shares_count',
        'declared_date',
        'payment_date',
        'declared_by',
        'notes',
        'tax_amount',
        'net_amount',
        'status',
    ];

    protected $casts = [
        'declared_date' => 'date',
        'payment_date' => 'date',
        'total_amount' => 'decimal:2',
        'dividend_per_share' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function transactions()
    {
        return $this->hasMany(DividendTransaction::class);
    }

    public function declarer()
    {
        return $this->belongsTo(User::class, 'declared_by');
    }
}
