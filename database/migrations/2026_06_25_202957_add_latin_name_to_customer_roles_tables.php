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
        $tables = ['borrowers', 'co_borrowers', 'guarantors', 'investors', 'savers'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('latin_name')->nullable()->after('last_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['borrowers', 'co_borrowers', 'guarantors', 'investors', 'savers'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('latin_name');
            });
        }
    }
};
