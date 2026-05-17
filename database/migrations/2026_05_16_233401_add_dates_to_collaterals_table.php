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
        Schema::table('collaterals', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('currency')->comment('Date collateral was received');
            $table->date('end_date')->nullable()->after('start_date')->comment('Date collateral was returned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collaterals', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
