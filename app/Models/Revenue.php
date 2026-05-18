<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Revenue extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'revenue_category_id',
        'loan_id',
        'amount',
        'currency',
        'transaction_date',
        'reference_no',
        'payment_method',
        'description',
        'status',
        'repayment_transaction_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function revenue_category()
    {
        return $this->belongsTo(RevenueCategory::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    protected static function booted()
    {
        static::creating(function ($revenue) {
            if (empty($revenue->reference_no)) {
                $datePart = now()->toDateString();
                $dateFormatted = str_replace('-', '', $datePart);
                
                $lastRevenue = static::whereDate('created_at', now()->toDateString())
                    ->latest('id')
                    ->first();

                $sequence = 1;
                if ($lastRevenue && $lastRevenue->reference_no) {
                    $parts = explode('-', $lastRevenue->reference_no);
                    if (count($parts) === 3) {
                        $sequence = (int) $parts[2] + 1;
                    }
                }

                $revenue->reference_no = 'REV-' . $dateFormatted . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
