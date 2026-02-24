<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('saving_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('saving_accounts', 'saver_id')) {
                $table->foreignId('saver_id')->nullable()->after('borrower_id')->constrained('savers')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saving_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('saving_accounts', 'saver_id')) {
                $table->dropForeign(['saver_id']);
                $table->dropColumn('saver_id');
            }
        });
    }
};
