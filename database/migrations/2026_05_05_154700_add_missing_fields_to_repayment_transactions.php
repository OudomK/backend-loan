<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('repayment_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('repayment_transactions', 'waived_amount')) {
                $table->decimal('waived_amount', 15, 2)->default(0)->after('amount_paid');
            }
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
        Schema::table('repayment_transactions', function (Blueprint $table) {
            $table->dropColumn(['waived_amount', 'fee_paid']);
        });
    }
};
