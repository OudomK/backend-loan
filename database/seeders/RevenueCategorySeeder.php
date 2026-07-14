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
            ['name' => 'Interest Income', 'slug' => 'interest_income', 'group_name' => 'Operating Income', 'sort_order' => 1],
            ['name' => 'Admin Fee', 'slug' => 'admin_fee', 'group_name' => 'Operating Income', 'sort_order' => 2],
            ['name' => 'Penalty Income', 'slug' => 'penalty_income', 'group_name' => 'Other Revenue', 'sort_order' => 3],
            ['name' => 'Service Fees', 'slug' => 'service_fees', 'group_name' => 'Revenue', 'sort_order' => 4],
            ['name' => 'Commission Income', 'slug' => 'commission_income', 'group_name' => 'Revenue', 'sort_order' => 5],
            ['name' => 'Recovery Income', 'slug' => 'recovery_income', 'group_name' => 'Other Revenue', 'sort_order' => 6],
            ['name' => 'Other Revenue', 'slug' => 'other_revenue', 'group_name' => 'Other Revenue', 'sort_order' => 7],
        ];

        foreach ($categories as $category) {
            RevenueCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
