<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'name',
        'gender',
        'dob',
        'phone',
        'email',
        'address',
        'position_id',
        'salary',
        'date_joined',
        'status'
    ];

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }
}
