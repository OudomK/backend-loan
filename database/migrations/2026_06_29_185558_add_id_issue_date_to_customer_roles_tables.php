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
        $tables = ['customers', 'borrowers', 'guarantors', 'co_borrowers', 'savers', 'investors'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->date('id_issue_date')->nullable()->after('id_number');
            });
        }
    }

    public function down(): void
    {
        $tables = ['customers', 'borrowers', 'guarantors', 'co_borrowers', 'savers', 'investors'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('id_issue_date');
            });
        }
    }
};
