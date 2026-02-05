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
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'loan_officer_id')) {
                $table->foreignId('loan_officer_id')->nullable()->constrained('loan_officers')->onDelete('set null');
            }
            if (!Schema::hasColumn('loans', 'admin_fee')) {
                $table->decimal('admin_fee', 15, 2)->default(0);
            }

            // Remove old collateral columns if they exist
            $columnsToDrop = [];
            foreach (['collateral_type', 'collateral_value', 'collateral_currency'] as $col) {
                if (Schema::hasColumn('loans', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'loan_officer_id')) {
                $table->dropConstrainedForeignId('loan_officer_id');
            }
            if (Schema::hasColumn('loans', 'admin_fee')) {
                $table->dropColumn('admin_fee');
            }

            if (!Schema::hasColumn('loans', 'collateral_type')) {
                $table->string('collateral_type')->nullable();
            }
            if (!Schema::hasColumn('loans', 'collateral_value')) {
                $table->decimal('collateral_value', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('loans', 'collateral_currency')) {
                $table->string('collateral_currency')->nullable();
            }
        });
    }
};
