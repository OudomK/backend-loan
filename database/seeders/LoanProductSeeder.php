<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LoanProduct;

class LoanProductSeeder extends Seeder
{
    public function run(): void
    {
        LoanProduct::updateOrCreate(
            ['code' => 'PL-001'],
            [
                'name' => 'Personal Loan',
                'description' => 'Standard personal loan for individual needs',
                'interest_rate' => 1.2,
                'fee_percentage' => 1.0,
                'duration_months' => 12,
                'repayment_method' => 'annuity_monthly',
                'is_active' => true,
            ]
        );

        LoanProduct::updateOrCreate(
            ['code' => 'BL-001'],
            [
                'name' => 'Business Loan',
                'description' => 'Loan for small and medium enterprises',
                'interest_rate' => 1.5,
                'fee_percentage' => 2.0,
                'duration_months' => 24,
                'repayment_method' => 'annuity_monthly',
                'is_active' => true,
            ]
        );

        LoanProduct::updateOrCreate(
            ['code' => 'AG-001'],
            [
                'name' => 'Agriculture Loan',
                'description' => 'Loan for farming and agricultural activities',
                'interest_rate' => 1.0,
                'fee_percentage' => 0.5,
                'duration_months' => 6,
                'repayment_method' => 'Balloon',
                'is_active' => true,
            ]
        );
    }
}
