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
        Schema::table('repayment_transactions', function (Blueprint $table) {
            $table->string('repayment_type')->default('Normal')->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repayment_transactions', function (Blueprint $table) {
            $table->dropColumn('repayment_type');
        });
    }
};
