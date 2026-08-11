<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('penalty_late_since_date')->nullable();
        });

        // Preserve the active penalty period before late_since_date becomes a
        // schedule-only aging anchor that can advance to the next unpaid row.
        DB::table('loans')
            ->whereNotNull('late_since_date')
            ->whereNull('penalty_late_since_date')
            ->update([
                'penalty_late_since_date' => DB::raw('late_since_date'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('penalty_late_since_date');
        });
    }
};
