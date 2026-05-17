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
            ['name' => 'Commission Income', 'group_name' => 'Revenue', 'sort_order' => 1],
            ['name' => 'Service Fees', 'group_name' => 'Revenue', 'sort_order' => 2],
            ['name' => 'Penalty Income', 'group_name' => 'Revenue', 'sort_order' => 3],
        ];

        foreach ($categories as $category) {
            RevenueCategory::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
