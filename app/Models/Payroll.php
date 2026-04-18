<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payroll extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'employee_id',
        'month_year',
        'salary',
        'currency',
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
