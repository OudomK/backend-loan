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
        Schema::table('payment_allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_allocations', 'penalty_applied')) {
                $table->decimal('penalty_applied', 15, 2)->default(0)->after('principal_applied');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            if (Schema::hasColumn('payment_allocations', 'penalty_applied')) {
                $table->dropColumn('penalty_applied');
            }
        });
    }
};
