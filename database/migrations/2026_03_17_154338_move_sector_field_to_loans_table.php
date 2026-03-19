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
        // 1. Remove sector from borrowers
        if (Schema::hasColumn('borrowers', 'sector')) {
            Schema::table('borrowers', function (Blueprint $table) {
                $table->dropColumn('sector');
            });
        }

        // 2. Add sector to loans
        if (!Schema::hasColumn('loans', 'sector')) {
            Schema::table('loans', function (Blueprint $table) {
                // adding it after loan product id or purpose
                $table->string('sector', 100)->nullable()->after('purpose');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Remove sector from loans
        if (Schema::hasColumn('loans', 'sector')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropColumn('sector');
            });
        }

        // 2. Add sector back to borrowers
        if (!Schema::hasColumn('borrowers', 'sector')) {
            Schema::table('borrowers', function (Blueprint $table) {
                $table->string('sector', 100)->nullable()->after('occupation');
            });
        }
    }
};
