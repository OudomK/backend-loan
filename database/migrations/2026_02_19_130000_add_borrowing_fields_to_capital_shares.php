<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('capital_shares', function (Blueprint $table) {
            $table->foreignId('lender_id')->nullable()->after('borrower_id')->constrained('lenders')->onDelete('set null');
            $table->string('transaction_no')->nullable()->after('id');
            $table->string('loan_account')->nullable()->after('transaction_no');
            $table->enum('category', ['Real Capital', 'Loan Capital'])->default('Loan Capital')->after('lender_id');
            $table->date('borrowing_date')->nullable()->after('category');
            $table->string('account_no')->nullable()->after('borrowing_date');
            $table->string('contract_no')->nullable()->after('account_no');
            $table->string('payment_method')->nullable()->after('contract_no');
            $table->date('first_pay_date')->nullable()->after('payment_method');
            $table->integer('term_months')->default(0)->after('first_pay_date');
            $table->decimal('amount', 15, 2)->default(0)->after('term_months');
            $table->decimal('interest_rate', 8, 4)->default(0)->after('amount');
            $table->string('int_pay_mode')->nullable()->after('interest_rate');
            $table->decimal('fee', 15, 2)->default(0)->after('int_pay_mode');
            $table->date('maturity_date')->nullable()->after('fee');
            $table->string('sl_term')->nullable()->after('maturity_date');
            $table->decimal('dividends', 15, 2)->default(0)->after('status');
            $table->decimal('balance', 15, 2)->default(0)->after('dividends');
        });
    }

    public function down(): void
    {
        Schema::table('capital_shares', function (Blueprint $table) {
            $table->dropForeign(['lender_id']);
            $table->dropColumn([
                'lender_id',
                'transaction_no',
                'loan_account',
                'category',
                'borrowing_date',
                'account_no',
                'contract_no',
                'payment_method',
                'first_pay_date',
                'term_months',
                'amount',
                'interest_rate',
                'int_pay_mode',
                'fee',
                'maturity_date',
                'sl_term',
                'dividends',
                'balance',
            ]);
        });
    }
};
