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
            $table->string('code')->nullable()->after('id');
            $table->string('type')->default('Full-time')->after('department'); // Full-time, Part-time, Contract, Internship
            $table->text('description')->nullable()->after('base_salary');
            $table->text('requirements')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn(['code', 'type', 'description', 'requirements']);
        });
    }
};
