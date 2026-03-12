<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->foreignId('reporting_to_id')->nullable()->after('requirements')
                ->constrained('positions')->nullOnDelete();
            $table->unsignedInteger('min_headcount')->nullable()->after('reporting_to_id');
            $table->unsignedInteger('max_headcount')->nullable()->after('min_headcount');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['reporting_to_id']);
            $table->dropColumn(['min_headcount', 'max_headcount']);
        });
    }
};
