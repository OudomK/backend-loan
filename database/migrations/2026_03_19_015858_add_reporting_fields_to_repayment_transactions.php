<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('repayment_transactions', function (Blueprint $table) {
            $table->decimal('paid_off_amount', 15, 2)->default(0)->after('prepayment_paid');
            $table->decimal('recovery_amount', 15, 2)->default(0)->after('paid_off_amount');
            $table->decimal('withdrawn_prepayment', 15, 2)->default(0)->after('recovery_amount');
        });

        // Backfill existing data
        // 1. Paid-Off Paid: principal_paid where type 'Pay Off'
        DB::table('repayment_transactions')
            ->where('repayment_type', 'Pay Off')
            ->update(['paid_off_amount' => DB::raw('principal_paid')]);

        // 2. Recovery: amount_paid where type 'Recovery'
        DB::table('repayment_transactions')
            ->where('repayment_type', 'Recovery')
            ->update(['recovery_amount' => DB::raw('amount_paid')]);

        // 3. Withdrawn Prepayment: amount_paid where type in ['Withdraw', 'Refinance', 'Reschedule']
        DB::table('repayment_transactions')
            ->whereIn('repayment_type', ['Withdraw', 'Refinance', 'Reschedule'])
            ->update(['withdrawn_prepayment' => DB::raw('amount_paid')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repayment_transactions', function (Blueprint $table) {
            $table->dropColumn(['paid_off_amount', 'recovery_amount', 'withdrawn_prepayment']);
        });
    }
};
