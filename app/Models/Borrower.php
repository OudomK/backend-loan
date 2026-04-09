<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Borrower extends Model
{
    use SoftDeletes;

    protected $table = 'borrowers';

    protected static function booted()
    {
        static::addGlobalScope('borrower', function ($builder) {
            $builder->where('customer_type', 'Borrower');
        });

        static::creating(function ($model) {
            $model->customer_type = 'Borrower';
        });
    }

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
        'status',
        'customer_type'
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

                if ($parsed !== false && $parsed->format($format) === $raw) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
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
        return $this->hasMany(Loan::class, 'borrower_id');
    }
}
