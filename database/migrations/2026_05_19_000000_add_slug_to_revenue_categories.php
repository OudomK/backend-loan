<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('revenue_categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Populate slugs for existing categories
        $categories = DB::table('revenue_categories')->get();
        foreach ($categories as $category) {
            $slugName = strtolower($category->name);
            if (str_contains($slugName, 'penalty')) {
                $categorySlug = 'penalty_income';
            } elseif (str_contains($slugName, 'service') && str_contains($slugName, 'fee')) {
                $categorySlug = 'service_fees';
            } elseif (str_contains($slugName, 'interest')) {
                $categorySlug = 'interest_income';
            } elseif (str_contains($slugName, 'admin')) {
                $categorySlug = 'admin_fee';
            } elseif (str_contains($slugName, 'commission')) {
                $categorySlug = 'commission_income';
            } elseif (str_contains($slugName, 'other')) {
                $categorySlug = 'other_revenue';
            } else {
                $categorySlug = Str::slug($category->name, '_');
            }
            
            // Ensure slug is unique
            $baseSlug = $categorySlug ?: 'category';
            $tempSlug = $baseSlug;
            $counter = 1;
            while (DB::table('revenue_categories')->where('slug', $tempSlug)->where('id', '!=', $category->id)->exists()) {
                $tempSlug = $baseSlug . '_' . $counter;
                $counter++;
            }
            
            DB::table('revenue_categories')->where('id', $category->id)->update(['slug' => $tempSlug]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revenue_categories', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
