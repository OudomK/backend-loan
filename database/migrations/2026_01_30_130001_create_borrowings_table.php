<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('borrowings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_id')->constrained('lenders')->onDelete('cascade');
            $table->date('borrowing_date');
            $table->string('account_no')->nullable();
            $table->string('payment_method'); // Balloon, Declining, Negotiable
            $table->date('first_pay_date')->nullable();
            $table->string('currency', 10);
            $table->integer('term_months');
            $table->decimal('amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('fee', 15, 2)->default(0);
            $table->date('maturity_date')->nullable();
            $table->string('sl_term')->nullable(); // Short Term, Long Term
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowings');
    }
};
