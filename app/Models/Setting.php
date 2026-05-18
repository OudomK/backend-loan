<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value'
    ];
}
