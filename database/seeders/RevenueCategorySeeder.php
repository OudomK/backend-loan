<?php

namespace Database\Seeders;

use App\Models\RevenueCategory;
use Illuminate\Database\Seeder;

class RevenueCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Commission Income', 'slug' => 'commission_income', 'group_name' => 'Revenue', 'sort_order' => 1],
            ['name' => 'Service Fees', 'slug' => 'service_fees', 'group_name' => 'Revenue', 'sort_order' => 2],
            ['name' => 'Penalty Income', 'slug' => 'penalty_income', 'group_name' => 'Revenue', 'sort_order' => 3],
        ];

        foreach ($categories as $category) {
            RevenueCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
