<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'code',
        'name',
        'department',
        'type',
        'base_salary',
        'description',
        'requirements',
        'status',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
