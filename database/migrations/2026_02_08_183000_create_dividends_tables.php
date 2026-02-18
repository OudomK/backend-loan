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
        Schema::create('dividends', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('dividend_per_share', 15, 4); // 4 decimals for precision
            $table->string('currency', 20);
            $table->integer('total_shares_count');
            $table->date('declared_date');
            $table->enum('status', ['Draft', 'Completed'])->default('Draft');
            $table->timestamps();
        });

        Schema::create('dividend_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dividend_id')->constrained()->onDelete('cascade');
            $table->foreignId('capital_share_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 20);
            $table->enum('status', ['Pending', 'Paid'])->default('Pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable(); // Cash, Saving Account
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dividend_transactions');
        Schema::dropIfExists('dividends');
    }
};
