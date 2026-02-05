<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('payment_date');
        });

        Schema::table('repayment_transactions', function (Blueprint $table) {
            $table->index('transaction_date');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->index('status');
            $table->index('loan_officer_id');
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payment_date']);
        });

        Schema::table('repayment_transactions', function (Blueprint $table) {
            $table->dropIndex(['transaction_date']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['loan_officer_id']);
            $table->dropIndex(['start_date']);
        });
    }
};
