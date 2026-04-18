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
        Schema::table('positions', function (Blueprint $table) {
            if (! Schema::hasColumn('positions', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('base_salary');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('salary');
            }
        });

        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('salary');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'currency')) {
                $table->dropColumn('currency');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'currency')) {
                $table->dropColumn('currency');
            }
        });

        Schema::table('positions', function (Blueprint $table) {
            if (Schema::hasColumn('positions', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
