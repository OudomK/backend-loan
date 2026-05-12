<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miscellaneous_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('miscellaneous_transactions', 'loan_id')) {
                $table->foreignId('loan_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('loans')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('miscellaneous_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('miscellaneous_transactions', 'loan_id')) {
                $table->dropConstrainedForeignId('loan_id');
            }
        });
    }
};
