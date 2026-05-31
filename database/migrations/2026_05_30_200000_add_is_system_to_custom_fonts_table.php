<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('custom_fonts', function (Blueprint $table) {
            if (! Schema::hasColumn('custom_fonts', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('custom_fonts', function (Blueprint $table) {
            if (Schema::hasColumn('custom_fonts', 'is_system')) {
                $table->dropColumn('is_system');
            }
        });
    }
};
