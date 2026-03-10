<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LoanProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['name' => 'General Loan', 'code' => 'GEN', 'description' => 'Standard all-purpose loan'],
            ['name' => 'Business Loan', 'code' => 'BUS', 'description' => 'Loan for small and medium enterprises'],
            ['name' => 'Agriculture Loan', 'code' => 'AGR', 'description' => 'Loan for agricultural purposes'],
            ['name' => 'Staff Loan', 'code' => 'STF', 'description' => 'Internal loan for employees'],
            ['name' => 'Education Loan', 'code' => 'EDU', 'description' => 'Loan for educational or tuition fees'],
        ];

        foreach ($products as $product) {
            \App\Models\LoanProduct::firstOrCreate(
                ['code' => $product['code']],
                ['name' => $product['name'], 'description' => $product['description'], 'is_active' => true]
            );
        }
    }
}
