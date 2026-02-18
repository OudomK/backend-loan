<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DividendTransaction extends Model
{
    protected $fillable = [
        'dividend_id',
        'capital_share_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'payment_method',
    ];

    public function dividend()
    {
        return $this->belongsTo(Dividend::class);
    }

    public function share()
    {
        return $this->belongsTo(CapitalShare::class, 'capital_share_id');
    }
}
