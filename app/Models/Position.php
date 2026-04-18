<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'department',
        'type',
        'base_salary',
        'currency',
        'description',
        'requirements',
        'status',
        'reporting_to_id',
        'min_headcount',
        'max_headcount',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function reportingTo()
    {
        return $this->belongsTo(Position::class, 'reporting_to_id');
    }

    public function reportsToMe()
    {
        return $this->hasMany(Position::class, 'reporting_to_id');
    }
}
