<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalShareTransaction extends Model
{
    protected $fillable = [
        'capital_share_id',
        'transaction_type',
        'amount',
        'share_qty',
        'payment_method',
        'transaction_date',
        'reference_no',
        'description',
        'performed_by',
    ];

    public function capitalShare(): BelongsTo
    {
        return $this->belongsTo(CapitalShare::class);
    }
}
