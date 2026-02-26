<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanOfficer extends Model
{

    protected $fillable = ['employee_id', 'name', 'phone', 'status'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
