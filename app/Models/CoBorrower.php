<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoBorrower extends Model
{
    use SoftDeletes, Auditable;


    protected $appends = ['formatted_gender'];
    protected $fillable = [
        'row_no',
        'customer_code',
        'first_name',
        'last_name',
        'latin_name',
        'gender',
        'marital_status',
        'age',
        'dob',
        'phone',
        'id_type',
        'id_number',
        'id_issue_date',
        'id_expiry',
        'occupation',
        'village',
        'commune',
        'district',
        'province',
        'photo',
        'status'
    ];

    public function getAgeAttribute()
    {
        if (blank($this->dob)) {
            return $this->attributes['age'] ?? null;
        }

        try {
            return Carbon::parse($this->dob)->age;
        } catch (\Exception $e) {
            return $this->attributes['age'] ?? null;
        }
    }

    public function getFormattedGenderAttribute()
    {
        return empty($this->gender) ? '' : strtoupper(substr($this->gender, 0, 1));
    }

    public function getPhoneAttribute(mixed $value): ?string
    {
        return \App\Support\FormatHelper::formatPhoneNumber((string)$value);
    }

    public function setDobAttribute(mixed $value)
    {
        $this->attributes['dob'] = $this->normalizeDateInput($value);
    }

    public function setIdExpiryAttribute(mixed $value)
    {
        $this->attributes['id_expiry'] = $this->normalizeDateInput($value);
    }

    public function setIdIssueDateAttribute(mixed $value)
    {
        $this->attributes['id_issue_date'] = $this->normalizeDateInput($value);
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

    public function latestLoan()
    {
        return $this->hasOne(Loan::class, 'co_borrower_id')->latestOfMany();
    }
}
