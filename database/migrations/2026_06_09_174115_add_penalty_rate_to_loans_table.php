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
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('penalty_rate', 15, 2)->nullable()->after('interest_rate');
        });

        // Populate existing loans with current settings
        $usdRate = \App\Models\Setting::where('key', 'default_penalty_usd')->value('value') ?? 2.5;
        $khrRate = \App\Models\Setting::where('key', 'default_penalty_khr')->value('value') ?? 10000;

        \Illuminate\Support\Facades\DB::table('loans')
            ->where('currency', 'USD')
            ->update(['penalty_rate' => $usdRate]);

        \Illuminate\Support\Facades\DB::table('loans')
            ->where('currency', 'KHR')
            ->update(['penalty_rate' => $khrRate]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('penalty_rate');
        });
    }
};
