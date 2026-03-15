<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Fee: support one-time (pay once) and monthly (pay per installment).
     * - loans.admin_fee_type: 'one_time' | 'monthly'
     * - repayment_transactions.fee_paid: fee collected in that transaction
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'admin_fee_type')) {
                $table->string('admin_fee_type', 20)->default('one_time')->after('admin_fee');
            }
        });

        Schema::table('repayment_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('repayment_transactions', 'fee_paid')) {
                $table->decimal('fee_paid', 15, 2)->default(0)->after('penalty_paid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'admin_fee_type')) {
                $table->dropColumn('admin_fee_type');
            }
        });
        Schema::table('repayment_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('repayment_transactions', 'fee_paid')) {
                $table->dropColumn('fee_paid');
            }
        });
    }
};
