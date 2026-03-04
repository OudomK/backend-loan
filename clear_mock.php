<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    'leave_requests',
    'miscellaneous_transactions',
    'customers',
    'employees',
    'positions'
];

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        DB::table($table)->truncate();
        echo "Truncated $table\n";
    }
}

DB::statement('SET FOREIGN_KEY_CHECKS=1;');
