<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collateral extends Model
{
    protected $fillable = ['loan_id', 'type', 'owner_name', 'value', 'currency', 'description'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
