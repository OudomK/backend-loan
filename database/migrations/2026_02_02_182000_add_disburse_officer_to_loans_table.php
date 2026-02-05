<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'disbursed_by_officer_id')) {
                $table->foreignId('disbursed_by_officer_id')->nullable()->after('loan_officer_id')->constrained('loan_officers')->onDelete('set null');
            }
        });

        // Backfill existing data: Original officer = Current officer
        DB::statement('UPDATE loans SET disbursed_by_officer_id = loan_officer_id WHERE disbursed_by_officer_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disbursed_by_officer_id');
        });
    }
};
