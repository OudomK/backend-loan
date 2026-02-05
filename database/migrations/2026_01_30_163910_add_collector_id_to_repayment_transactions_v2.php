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
            $table->foreignId('collector_id')->nullable()->after('loan_id')->constrained('loan_officers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repayment_transactions', function (Blueprint $table) {
            //
        });
    }
};
