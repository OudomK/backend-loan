<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $indexes = Schema::getIndexes('borrowers');
            $indexNames = collect($indexes)->pluck('name');

            if (!$indexNames->contains('borrowers_status_customer_type_index')) {
                $table->index(['status', 'customer_type']);
            }
            if (!$indexNames->contains('borrowers_customer_code_index')) {
                $table->index('customer_code');
            }
        });

        Schema::table('loans', function (Blueprint $table) {
            $indexes = Schema::getIndexes('loans');
            $indexNames = collect($indexes)->pluck('name');

            if (!$indexNames->contains('loans_status_index')) {
                $table->index('status');
            }
            if (!$indexNames->contains('loans_start_date_index')) {
                $table->index('start_date');
            }
            if (!$indexNames->contains('loans_borrower_id_index')) {
                $table->index('borrower_id');
            }
        });

        Schema::table('repayment_transactions', function (Blueprint $table) {
            $indexes = Schema::getIndexes('repayment_transactions');
            $indexNames = collect($indexes)->pluck('name');

            if (!$indexNames->contains('repayment_transactions_transaction_date_index')) {
                $table->index('transaction_date');
            }
            if (!$indexNames->contains('repayment_transactions_loan_id_index')) {
                $table->index('loan_id');
            }
        });

        Schema::table('saving_accounts', function (Blueprint $table) {
            $indexes = Schema::getIndexes('saving_accounts');
            $indexNames = collect($indexes)->pluck('name');

            if (!$indexNames->contains('saving_accounts_status_index')) {
                $table->index('status');
            }
            if (!$indexNames->contains('saving_accounts_borrower_id_index')) {
                $table->index('borrower_id');
            }
        });
    }

    public function down(): void
    {
    }
};
