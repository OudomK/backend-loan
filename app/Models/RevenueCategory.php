<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RevenueCategory extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'slug',
        'group_name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $slugName = strtolower($category->name);
                if (str_contains($slugName, 'penalty')) {
                    $category->slug = 'penalty_income';
                } elseif (str_contains($slugName, 'service') && str_contains($slugName, 'fee')) {
                    $category->slug = 'service_fees';
                } elseif (str_contains($slugName, 'interest')) {
                    $category->slug = 'interest_income';
                } elseif (str_contains($slugName, 'admin')) {
                    $category->slug = 'admin_fee';
                } elseif (str_contains($slugName, 'commission')) {
                    $category->slug = 'commission_income';
                } elseif (str_contains($slugName, 'other')) {
                    $category->slug = 'other_revenue';
                } else {
                    $category->slug = \Illuminate\Support\Str::slug($category->name, '_');
                }

                // Ensure slug is unique
                $baseSlug = $category->slug ?: 'category';
                $tempSlug = $baseSlug;
                $counter = 1;
                while (static::where('slug', $tempSlug)->exists()) {
                    $tempSlug = $baseSlug . '_' . $counter;
                    $counter++;
                }
                $category->slug = $tempSlug;
            }
        });
    }

    public function revenues()
    {
        return $this->hasMany(Revenue::class);
    }
}
