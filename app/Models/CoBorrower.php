<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoBorrower extends Model
{
    use SoftDeletes;


    protected $fillable = [
        'customer_code',
        'first_name',
        'last_name',
        'gender',
        'marital_status',
        'age',
        'dob',
        'phone',
        'id_type',
        'id_number',
        'id_expiry',
        'occupation',
        'village',
        'commune',
        'district',
        'province',
        'photo',
        'status'
    ];

    public function setDobAttribute($value)
    {
        $this->attributes['dob'] = $this->normalizeDateInput($value);
    }

    public function setIdExpiryAttribute($value)
    {
        $this->attributes['id_expiry'] = $this->normalizeDateInput($value);
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $raw);
            } catch (\Throwable) {
                continue;
            }

            if ($parsed !== false && $parsed->format($format) === $raw) {
                return $parsed->format('Y-m-d');
            }
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'co_borrower_id');
    }
}
