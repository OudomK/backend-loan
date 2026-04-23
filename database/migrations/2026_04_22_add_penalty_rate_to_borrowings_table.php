<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->decimal('penalty_rate', 8, 4)->default(0)->after('interest_rate')
                  ->comment('Daily penalty rate in % (e.g. 0.05 = 0.05% per day)');
        });
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn('penalty_rate');
        });
    }
};
