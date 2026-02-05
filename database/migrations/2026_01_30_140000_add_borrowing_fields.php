<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->string('transaction_no')->nullable()->after('id');
            $table->string('loan_account')->nullable()->after('transaction_no');
            $table->enum('category', ['Real Capital', 'Loan Capital'])->default('Loan Capital')->after('lender_id');
            $table->string('int_pay_mode')->nullable()->after('interest_rate');
            $table->string('contract_no')->nullable()->after('account_no');
            $table->decimal('late_principal', 15, 2)->default(0)->after('status');
            $table->decimal('loan_interest', 15, 2)->default(0)->after('late_principal');
        });
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn([
                'transaction_no',
                'loan_account',
                'category',
                'int_pay_mode',
                'contract_no',
                'late_principal',
                'loan_interest',
            ]);
        });
    }
};
