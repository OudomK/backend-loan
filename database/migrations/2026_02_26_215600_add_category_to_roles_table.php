<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('roles', 'category')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('category', 20)->default('admin')->after('guard_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'category')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
