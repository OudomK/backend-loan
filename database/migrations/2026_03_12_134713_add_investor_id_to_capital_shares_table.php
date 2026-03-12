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
        Schema::table('capital_shares', function (Blueprint $table) {
            if (!Schema::hasColumn('capital_shares', 'investor_id')) {
                $table->foreignId('investor_id')->nullable()->after('lender_id')->constrained('investors')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capital_shares', function (Blueprint $table) {
            if (Schema::hasColumn('capital_shares', 'investor_id')) {
                $table->dropForeign(['investor_id']);
                $table->dropColumn('investor_id');
            }
        });
    }
};
