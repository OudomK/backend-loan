<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalShare extends Model
{
    protected $fillable = [
        'borrower_id',
        'holder_id',
        'certificate_no',
        'share_qty',
        'par_value',
        'total_capital',
        'currency',
        'purchase_date',
        'status',
    ];

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }
}
