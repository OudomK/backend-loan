<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $tables = [
            'repayment_transactions',
            'payments',
            'loans',
            'guarantors',
            'co_borrowers',
            'borrowers',
            'collaterals',
            'loan_officers',
            'dividend_transactions',
            'dividends',
            'dividend_schedules',
            'saving_transactions',
            'saving_accounts',
            'capital_share_transactions',
            'capital_shares',
            'savers',
            'investors',
            'lenders',
            'borrowings',
            'borrowing_repayments',
            'payrolls',
            'miscellaneous_transactions',
            'customers',
            'employees',
            'positions'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        // No practical rollback for truncate
    }
};
