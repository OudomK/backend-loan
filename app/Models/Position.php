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
