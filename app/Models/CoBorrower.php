<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoBorrower extends Model
{
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
        return $this->hasMany(Loan::class, 'co_borrower_id');
    }
}
