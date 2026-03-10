<?php

namespace Database\Seeders;

use App\Models\Borrower;
use App\Models\CoBorrower;
use App\Models\Guarantor;
use App\Models\Investor;
use Illuminate\Database\Seeder;

class BorrowerCoBorrowerGuarantorSeeder extends Seeder
{
    /**
     * Creates 1 Borrower, 1 Co-borrower, 1 Guarantor, 1 Investor for testing.
     */
    public function run(): void
    {
        // 1. Borrower
        Borrower::firstOrCreate(
            ['id_number' => '19900615001234'],
            [
                'customer_code' => 'QF-001',
                'first_name' => 'Sok',
                'last_name' => 'Dara',
                'gender' => 'Male',
                'marital_status' => 'Married',
                'age' => 34,
                'dob' => '15/06/1990',
                'phone' => '012345678',
                'id_type' => 'National ID',
                'id_number' => '19900615001234',
                'id_expiry' => '01/12/2030',
                'occupation' => 'Merchant',
                'village' => 'Phum 1',
                'commune' => 'Sangkat Tek Thla',
                'district' => 'Khan Russey Keo',
                'province' => 'Phnom Penh',
                'status' => 'Active',
                'customer_type' => 'Borrower',
            ]
        );

        // 2. Co-borrower
        CoBorrower::firstOrCreate(
            ['id_number' => '19920820005678'],
            [
                'customer_code' => 'QF-CO-001',
                'first_name' => 'Srey',
                'last_name' => 'Mom',
                'gender' => 'Female',
                'marital_status' => 'Single',
                'age' => 32,
                'dob' => '20/08/1992',
                'phone' => '098765432',
                'id_type' => 'National ID',
                'id_number' => '19920820005678',
                'id_expiry' => '01/12/2030',
                'occupation' => 'Teacher',
                'village' => 'Phum 2',
                'commune' => 'Sangkat Toul Sangke',
                'district' => 'Khan Russey Keo',
                'province' => 'Phnom Penh',
                'status' => 'Active',
            ]
        );

        // 3. Guarantor
        Guarantor::firstOrCreate(
            ['id_number' => '19850310009999'],
            [
                'customer_code' => 'QF-GU-001',
                'first_name' => 'Heng',
                'last_name' => 'Sothy',
                'gender' => 'Male',
                'marital_status' => 'Married',
                'age' => 39,
                'dob' => '10/03/1985',
                'phone' => '077123456',
                'id_type' => 'National ID',
                'id_number' => '19850310009999',
                'id_expiry' => '01/12/2030',
                'occupation' => 'Business Owner',
                'village' => 'Phum 3',
                'commune' => 'Sangkat Chroy Changvar',
                'district' => 'Khan Chroy Changvar',
                'province' => 'Phnom Penh',
                'status' => 'Active',
            ]
        );

        // 4. Investor
        Investor::firstOrCreate(
            ['id_number' => '19880105111111'],
            [
                'customer_code' => 'INV0001',
                'first_name' => 'Ly',
                'last_name' => 'Vannak',
                'gender' => 'Male',
                'marital_status' => 'Married',
                'age' => 36,
                'dob' => '05/01/1988',
                'phone' => '069123456',
                'id_type' => 'National ID',
                'id_number' => '19880105111111',
                'id_expiry' => '01/12/2030',
                'occupation' => 'Investor',
                'village' => 'Phum 4',
                'commune' => 'Sangkat Boeng Keng Kang',
                'district' => 'Khan Boeng Keng Kang',
                'province' => 'Phnom Penh',
                'status' => 'Active',
                'customer_type' => 'Investor',
            ]
        );
    }
}
