<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class CapitalShareTransaction extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'capital_share_id',
        'transaction_type',
        'amount',
        'share_qty',
        'payment_method',
        'transaction_date',
        'reference_no',
        'description',
        'performed_by',
    ];

    protected $casts = [
        'share_qty' => 'float',
    ];

    public function capitalShare(): BelongsTo
    {
        return $this->belongsTo(CapitalShare::class);
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
