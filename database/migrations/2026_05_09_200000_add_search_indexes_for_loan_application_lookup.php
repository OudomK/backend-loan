<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('co_borrowers', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('co_borrowers'))->pluck('name');

            if (!$indexes->contains('co_borrowers_customer_code_index')) {
                $table->index('customer_code');
            }
            if (!$indexes->contains('co_borrowers_phone_index')) {
                $table->index('phone');
            }
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('guarantors'))->pluck('name');

            if (!$indexes->contains('guarantors_customer_code_index')) {
                $table->index('customer_code');
            }
            if (!$indexes->contains('guarantors_phone_index')) {
                $table->index('phone');
            }
        });

        Schema::table('loans', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('loans'))->pluck('name');

            if (!$indexes->contains('loans_loan_code_index')) {
                $table->index('loan_code');
            }
            if (!$indexes->contains('loans_co_borrower_id_index')) {
                $table->index('co_borrower_id');
            }
            if (!$indexes->contains('loans_guarantor_id_index')) {
                $table->index('guarantor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('co_borrowers', function (Blueprint $table) {
            $table->dropIndex('co_borrowers_customer_code_index');
            $table->dropIndex('co_borrowers_phone_index');
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->dropIndex('guarantors_customer_code_index');
            $table->dropIndex('guarantors_phone_index');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_loan_code_index');
            $table->dropIndex('loans_co_borrower_id_index');
            $table->dropIndex('loans_guarantor_id_index');
        });
    }
};
