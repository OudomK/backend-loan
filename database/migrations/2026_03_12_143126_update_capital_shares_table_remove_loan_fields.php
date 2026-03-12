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
        Schema::table('capital_shares', function (Blueprint $table) {
            $table->dropColumn([
                'fee', 
                'interest_rate', 
                'term_months', 
                'payment_method', 
                'first_pay_date', 
                'sl_term', 
                'maturity_date'
            ]);
            
            $table->decimal('total_dividend_paid', 15, 2)->default(0)->after('dividends');
            $table->date('last_dividend_date')->nullable()->after('total_dividend_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capital_shares', function (Blueprint $table) {
            $table->dropColumn(['total_dividend_paid', 'last_dividend_date']);
            
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->integer('term_months')->default(0);
            $table->string('payment_method')->nullable();
            $table->date('first_pay_date')->nullable();
            $table->string('sl_term')->nullable();
            $table->date('maturity_date')->nullable();
        });
    }
};
