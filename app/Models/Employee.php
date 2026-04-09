<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'name_kh',
        'employee_code',
        'gender',
        'photo', // Profile picture
        'dob',
        'id_card_number',
        'marital_status',
        'number_of_children',
        'phone',
        'email',
        'address',
        'position_id',
        'employment_type', // Full-time, etc.
        'contract_end_date',
        'working_days_per_week',
        'salary',
        'bank_name',
        'bank_account_number',
        'nssf_id', // National Social Security Fund ID
        'emergency_contact_name',
        'emergency_contact_phone',
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
