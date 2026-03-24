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
        Schema::table('loan_products', function (Blueprint $table) {
            $table->decimal('interest_rate', 8, 2)->nullable();
            $table->decimal('fee_percentage', 8, 2)->nullable();
            $table->integer('duration_months')->nullable();
            $table->string('repayment_method')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'fee_percentage', 'duration_months', 'repayment_method']);
        });
    }
};
