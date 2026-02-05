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
            $table->foreignId('borrower_id')->nullable()->change();
            $table->decimal('amount', 15, 2)->nullable()->change();
            $table->decimal('interest_rate', 5, 2)->nullable()->change();
            $table->integer('duration_months')->nullable()->change();
            $table->decimal('monthly_payment', 15, 2)->nullable()->change();
            $table->date('start_date')->nullable()->change();
            $table->string('repayment_method')->nullable()->change();
            $table->string('currency')->nullable()->change();
            $table->string('payment_frequency')->nullable()->change();
        });

        Schema::table('collaterals', function (Blueprint $table) {
            $table->string('type')->nullable()->change();
            $table->decimal('value', 15, 2)->nullable()->change();
            $table->string('currency')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Restore required if needed, but usually we just keep it nullable for draft-like behavior
        });
    }
};
