<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Borrower extends Model
{

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
        if ($value) {
            try {
                $this->attributes['dob'] = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            } catch (\Exception $e) {
                $this->attributes['dob'] = null;
            }
        }
    }

    public function setIdExpiryAttribute($value)
    {
        if ($value) {
            try {
                $this->attributes['id_expiry'] = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            } catch (\Exception $e) {
                $this->attributes['id_expiry'] = null;
            }
        }
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'borrower_id');
    }
}
