<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('collaterals', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('collaterals', function (Blueprint $table) {
            $table->dropColumn('owner_name');
        });
    }
};
