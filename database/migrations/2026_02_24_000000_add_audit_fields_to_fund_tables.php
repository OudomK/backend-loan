<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add saver_id, balance_after, performed_by, interest_earned to saving tables
        Schema::table('saving_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('saving_accounts', 'saver_id')) {
                $table->unsignedBigInteger('saver_id')->nullable()->after('borrower_id');
                $table->foreign('saver_id')->references('id')->on('savers')->onDelete('set null');
            }
            if (!Schema::hasColumn('saving_accounts', 'total_deposits')) {
                $table->decimal('total_deposits', 15, 2)->default(0)->after('balance');
            }
            if (!Schema::hasColumn('saving_accounts', 'total_withdrawals')) {
                $table->decimal('total_withdrawals', 15, 2)->default(0)->after('total_deposits');
            }
            if (!Schema::hasColumn('saving_accounts', 'interest_earned')) {
                $table->decimal('interest_earned', 15, 2)->default(0)->after('total_withdrawals');
            }
        });

        Schema::table('saving_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('saving_transactions', 'balance_after')) {
                $table->decimal('balance_after', 15, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('saving_transactions', 'performed_by')) {
                $table->unsignedBigInteger('performed_by')->nullable()->after('description');
            }
        });

        // Add performed_by to capital_share_transactions
        Schema::table('capital_share_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('capital_share_transactions', 'performed_by')) {
                $table->unsignedBigInteger('performed_by')->nullable()->after('description');
            }
        });

        // Make borrower_id nullable on saving_accounts (since we now use saver_id)
        Schema::table('saving_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('borrower_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('saving_accounts', function (Blueprint $table) {
            $table->dropForeign(['saver_id']);
            $table->dropColumn(['saver_id', 'total_deposits', 'total_withdrawals', 'interest_earned']);
        });

        Schema::table('saving_transactions', function (Blueprint $table) {
            $table->dropColumn(['balance_after', 'performed_by']);
        });

        Schema::table('capital_share_transactions', function (Blueprint $table) {
            $table->dropColumn(['performed_by']);
        });
    }
};
