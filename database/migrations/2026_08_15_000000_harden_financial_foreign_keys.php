<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('payments_loan_id_foreign');
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('restrict');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign('loans_borrower_id_foreign');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('restrict');
        });

        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropForeign('borrowings_lender_id_foreign');
            $table->foreign('lender_id')->references('id')->on('lenders')->onDelete('restrict');
        });

        Schema::table('capital_shares', function (Blueprint $table) {
            $table->dropForeign('capital_shares_borrower_id_foreign');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('restrict');
        });

        Schema::table('saving_accounts', function (Blueprint $table) {
            $table->dropForeign('saving_accounts_borrower_id_foreign');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saving_accounts', function (Blueprint $table) {
            $table->dropForeign('saving_accounts_borrower_id_foreign');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('cascade');
        });

        Schema::table('capital_shares', function (Blueprint $table) {
            $table->dropForeign('capital_shares_borrower_id_foreign');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('cascade');
        });

        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropForeign('borrowings_lender_id_foreign');
            $table->foreign('lender_id')->references('id')->on('lenders')->onDelete('cascade');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign('loans_borrower_id_foreign');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('cascade');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('payments_loan_id_foreign');
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
        });
    }
};
