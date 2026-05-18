<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lender extends Model
{
    use Auditable;

    protected $fillable = [
        'lender_code',
        'name',
        'lender_type',
        'phone',
        'address'
    ];

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }
}
