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
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'fee_amount')) {
                $table->decimal('fee_amount', 15, 2)->default(0)->after('interest_amount');
            }
            if (!Schema::hasColumn('payments', 'fee_paid')) {
                $table->decimal('fee_paid', 15, 2)->default(0)->after('fee_amount');
            }
            if (!Schema::hasColumn('payments', 'total_due')) {
                $table->decimal('total_due', 15, 2)->virtualAs('principal_amount + interest_amount + fee_amount')->after('fee_paid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'fee_paid', 'total_due']);
        });
    }
};
