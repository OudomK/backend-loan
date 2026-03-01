<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('capital_shares', function (Blueprint $table) {
            // Make original required fields nullable for a more flexible model
            $table->foreignId('borrower_id')->nullable()->change();
            $table->string('holder_id')->nullable()->change();
            $table->string('certificate_no')->nullable()->change();
            $table->date('purchase_date')->nullable()->change();
        });

        Schema::table('capital_share_transactions', function (Blueprint $table) {
            // Add performed_by column to transactions table
            if (!Schema::hasColumn('capital_share_transactions', 'performed_by')) {
                $table->foreignId('performed_by')->nullable()->after('description')->constrained('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('capital_shares', function (Blueprint $table) {
            $table->foreignId('borrower_id')->nullable(false)->change();
            $table->string('holder_id')->nullable(false)->change();
            $table->string('certificate_no')->nullable(false)->change();
            $table->date('purchase_date')->nullable(false)->change();
        });

        Schema::table('capital_share_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('capital_share_transactions', 'performed_by')) {
                $table->dropForeign(['performed_by']);
                $table->dropColumn('performed_by');
            }
        });
    }
};
