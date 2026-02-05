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
            $table->string('loan_code')->nullable()->after('id');
            $table->string('payment_frequency')->default('Monthly')->after('monthly_payment');
            $table->string('currency')->default('USD ($)')->after('amount');
            $table->string('repayment_method')->after('payment_frequency');

            // Collateral
            $table->string('collateral_type')->nullable()->after('guarantor_relationship');
            $table->decimal('collateral_value', 15, 2)->nullable()->after('collateral_type');
            $table->string('collateral_currency')->default('USD ($)')->after('collateral_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'loan_code',
                'payment_frequency',
                'currency',
                'repayment_method',
                'collateral_type',
                'collateral_value',
                'collateral_currency'
            ]);
        });
    }
};
