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
        Schema::create('repayment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->decimal('amount_paid', 15, 2);
            $table->decimal('principal_paid', 15, 2);
            $table->decimal('interest_paid', 15, 2);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->string('payment_method');
            $table->date('transaction_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repayment_transactions');
    }
};
