<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes, Auditable;
    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
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

    /**
     * Set the birth date.
     *
     * @param  string  $value
     * @return void
     */
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

    /**
     * Set the identity expiry date.
     *
     * @param  string  $value
     * @return void
     */
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
        return $this->hasMany(Loan::class, 'customer_id');
    }

    public function coBorrowerLoans()
    {
        return $this->hasMany(Loan::class, 'co_borrower_id');
    }

    public function guarantorLoans()
    {
        return $this->hasMany(Loan::class, 'guarantor_id');
    }
}
