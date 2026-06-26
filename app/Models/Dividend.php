<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dividend extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'total_amount',
        'dividend_per_share',
        'currency',
        'distribution_basis',
        'total_shares_count',
        'declared_date',
        'payment_date',
        'declared_by',
        'notes',
        'tax_amount',
        'net_amount',
        'status',
    ];

    protected $casts = [
        'declared_date' => 'date',
        'payment_date' => 'date',
        'total_amount' => 'decimal:2',
        'dividend_per_share' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    /**
     * Fields that CANNOT be changed once the dividend is distributed (Completed).
     */
    protected static array $lockedFields = [
        'total_amount',
        'dividend_per_share',
        'currency',
        'distribution_basis',
        'total_shares_count',
        'tax_amount',
        'net_amount',
    ];

    protected static function booted(): void
    {
        static::updating(function (Dividend $dividend) {
            if ($dividend->getOriginal('status') === 'Completed') {
                foreach (static::$lockedFields as $field) {
                    if ($dividend->isDirty($field)) {
                        throw new \RuntimeException(
                            "Cannot modify '{$field}' on a completed dividend (ID: {$dividend->id}). Financial data is locked after distribution."
                        );
                    }
                }
            }
        });

        static::deleting(function (Dividend $dividend) {
            if ($dividend->status === 'Completed') {
                throw new \RuntimeException(
                    "Cannot delete a completed dividend (ID: {$dividend->id}). It has already been distributed."
                );
            }
        });
    }

    public function isLocked(): bool
    {
        return $this->status === 'Completed';
    }

    public function transactions()
    {
        return $this->hasMany(DividendTransaction::class);
    }

    public function declarer()
    {
        return $this->belongsTo(User::class, 'declared_by');
    }
}
