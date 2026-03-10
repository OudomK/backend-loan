<?php

namespace Database\Seeders;

use App\Models\Lender;
use App\Models\Borrowing;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BorrowingSeeder extends Seeder
{
    /**
     * Creates 1 Lender and 1 Borrowing for testing.
     */
    public function run(): void
    {
        $lender = Lender::firstOrCreate(
            ['lender_code' => 'LND-001'],
            [
                'name' => 'Ly Vannak',
                'lender_type' => 'Individual',
                'phone' => '069123456',
                'address' => 'Phnom Penh',
            ]
        );

        $borrowingDate = Carbon::today()->subMonths(2);
        $termMonths = 12;
        $maturityDate = $borrowingDate->copy()->addMonths($termMonths);
        $firstPayDate = $borrowingDate->copy()->addMonth();

        Borrowing::firstOrCreate(
            [
                'lender_id' => $lender->id,
                'contract_no' => 'BOR-2025-001',
            ],
            [
                'transaction_no' => 'TXN-001',
                'loan_account' => null,
                'category' => 'Loan Capital',
                'borrowing_date' => $borrowingDate->toDateString(),
                'account_no' => 'ACC-001',
                'contract_no' => 'BOR-2025-001',
                'payment_method' => 'Declining',
                'int_pay_mode' => 'Monthly',
                'first_pay_date' => $firstPayDate->toDateString(),
                'currency' => 'USD',
                'term_months' => $termMonths,
                'amount' => 10000.00,
                'interest_rate' => 12.00,
                'fee' => 0,
                'maturity_date' => $maturityDate->toDateString(),
                'sl_term' => 'Short Term',
                'status' => 'active',
                'late_principal' => 0,
                'loan_interest' => 0,
            ]
        );
    }
}
