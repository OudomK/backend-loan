<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Saver extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'savers';
    protected $appends = ['formatted_gender'];

    protected static function booted()
    {
        static::addGlobalScope('saver', function ($builder) {
            $builder->where('customer_type', 'Saver');
        });

        static::creating(function ($model) {
            $model->customer_type = 'Saver';
        });
    }

    protected $fillable = [
        'customer_code',
        'first_name',
        'last_name',
        'latin_name',
        'nickname',
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
        'status',
        'customer_type'
    ];

    public function getFormattedGenderAttribute()
    {
        return empty($this->gender) ? '' : strtoupper(substr($this->gender, 0, 1));
    }

    public function setDobAttribute(mixed $value)
    {
        if ($value) {
            try {
                $this->attributes['dob'] = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            } catch (\Exception $e) {
                $this->attributes['dob'] = null;
            }
        }
    }

    public function setIdExpiryAttribute(mixed $value)
    {
        if ($value) {
            try {
                $this->attributes['id_expiry'] = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            } catch (\Exception $e) {
                $this->attributes['id_expiry'] = null;
            }
        }
    }

    public function setIdIssueDateAttribute(mixed $value)
    {
        if ($value) {
            try {
                $this->attributes['id_issue_date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            } catch (\Exception $e) {
                $this->attributes['id_issue_date'] = null;
            }
        }
    }

    public function savingAccounts()
    {
        return $this->hasMany(SavingAccount::class, 'saver_id');
    }
}
