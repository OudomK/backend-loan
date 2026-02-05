<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('loans', function (Blueprint $table) {
            // Drop old constraint and column
            // Some drivers might need dropForeign first, others just drop the column
            try {
                $table->dropForeign(['customer_id']);
            } catch (\Exception $e) {
            }
            try {
                $table->dropColumn('customer_id');
            } catch (\Exception $e) {
            }

            try {
                $table->dropForeign(['co_borrower_id']);
            } catch (\Exception $e) {
            }
            try {
                $table->dropColumn('co_borrower_id');
            } catch (\Exception $e) {
            }

            try {
                $table->dropForeign(['guarantor_id']);
            } catch (\Exception $e) {
            }
            try {
                $table->dropColumn('guarantor_id');
            } catch (\Exception $e) {
            }

            // Add new role-based relationships
            $table->foreignId('borrower_id')->after('id')->constrained('borrowers')->onDelete('cascade');
            $table->foreignId('co_borrower_id')->nullable()->after('status')->constrained('co_borrowers')->onDelete('set null');
            $table->foreignId('guarantor_id')->nullable()->after('co_borrower_id')->constrained('guarantors')->onDelete('set null');
        });
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['borrower_id']);
            $table->dropColumn('borrower_id');
            $table->dropForeign(['co_borrower_id']);
            $table->dropColumn('co_borrower_id');
            $table->dropForeign(['guarantor_id']);
            $table->dropColumn('guarantor_id');

            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('co_borrower_id')->nullable()->constrained('customers');
            $table->foreignId('guarantor_id')->nullable()->constrained('customers');
        });
        Schema::enableForeignKeyConstraints();
    }
};
