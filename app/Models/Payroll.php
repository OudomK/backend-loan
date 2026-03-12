<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{

    protected $fillable = [
        'employee_id',
        'month_year',
        'salary',
        'allowance',
        'bonus',
        'deduction',
        'total_payable',
        'status',
        'payment_date',
        'payment_method',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
