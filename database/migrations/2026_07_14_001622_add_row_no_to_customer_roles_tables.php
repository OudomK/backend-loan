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
        $tables = ['borrowers', 'co_borrowers', 'guarantors', 'investors'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->integer('row_no')->nullable()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['borrowers', 'co_borrowers', 'guarantors', 'investors'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('row_no');
            });
        }
    }
};
