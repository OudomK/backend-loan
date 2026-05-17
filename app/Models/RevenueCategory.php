<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RevenueCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'group_name',
        'description',
        'is_active',
        'sort_order',
    ];

    public function revenues()
    {
        return $this->hasMany(Revenue::class);
    }
}
