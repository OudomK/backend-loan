<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanOfficer extends Model
{
    protected $fillable = ['name', 'phone', 'status'];

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
