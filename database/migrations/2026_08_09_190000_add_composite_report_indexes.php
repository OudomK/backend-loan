<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->index(['status', 'currency', 'start_date'], 'loans_status_currency_start_idx');
            $table->index(['status', 'loan_officer_id', 'start_date'], 'loans_status_officer_start_idx');
            $table->index(['written_off_at', 'currency'], 'loans_written_off_currency_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['loan_id', 'payment_date', 'deleted_at'], 'payments_loan_date_deleted_idx');
        });

        Schema::table('repayment_transactions', function (Blueprint $table) {
            $table->index(['loan_id', 'transaction_date', 'deleted_at'], 'repayments_loan_date_deleted_idx');
            $table->index(['collector_id', 'transaction_date', 'deleted_at'], 'repayments_collector_date_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repayment_transactions', function (Blueprint $table) {
            $table->dropIndex('repayments_loan_date_deleted_idx');
            $table->dropIndex('repayments_collector_date_deleted_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_loan_date_deleted_idx');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_status_currency_start_idx');
            $table->dropIndex('loans_status_officer_start_idx');
            $table->dropIndex('loans_written_off_currency_idx');
        });
    }
};
