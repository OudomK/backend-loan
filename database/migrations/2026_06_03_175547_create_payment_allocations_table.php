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
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repayment_transaction_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_applied', 15, 2)->default(0);
            $table->decimal('fee_applied', 15, 2)->default(0);
            $table->decimal('interest_applied', 15, 2)->default(0);
            $table->decimal('principal_applied', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
