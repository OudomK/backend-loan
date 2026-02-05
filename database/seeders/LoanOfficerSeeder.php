<?php

namespace Database\Seeders;

use App\Models\LoanOfficer;
use Illuminate\Database\Seeder;

class LoanOfficerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LoanOfficer::create(['name' => 'John Doe', 'phone' => '012345678', 'status' => 'active']);
        LoanOfficer::create(['name' => 'Jane Smith', 'phone' => '098765432', 'status' => 'active']);
        LoanOfficer::create(['name' => 'Sok Dara', 'phone' => '011223344', 'status' => 'active']);
    }
}
