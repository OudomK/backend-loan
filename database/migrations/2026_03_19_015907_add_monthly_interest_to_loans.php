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
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('monthly_interest', 15, 2)->default(0)->after('interest_rate');
        });

        // Backfill: (amount * interest_rate) / 100
        DB::table('loans')->update([
            'monthly_interest' => DB::raw('(amount * interest_rate) / 100')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('monthly_interest');
        });
    }
};
