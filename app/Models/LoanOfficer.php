<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanOfficer extends Model
{
    use SoftDeletes, Auditable;


    protected $fillable = ['employee_id', 'name', 'phone', 'phone_2', 'phone_3', 'status', 'start_date', 'gender'];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = $value === null ? null : strtolower((string) $value);
    }

    public function getStatusAttribute(?string $value): ?string
    {
        return $value === null ? null : strtolower((string) $value);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
