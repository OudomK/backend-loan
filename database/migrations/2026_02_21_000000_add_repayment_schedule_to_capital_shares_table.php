<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('capital_shares', function (Blueprint $blueprint) {
            $blueprint->json('repayment_schedule')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capital_shares', function (Blueprint $blueprint) {
            $blueprint->dropColumn('repayment_schedule');
        });
    }
};
