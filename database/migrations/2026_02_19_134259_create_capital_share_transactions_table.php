<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('capital_share_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capital_share_id')->constrained()->onDelete('cascade');
            $table->enum('transaction_type', ['Initial', 'Deposit', 'Withdrawal', 'Dividend']);
            $table->decimal('amount', 15, 2);
            $table->integer('share_qty')->default(0);
            $table->string('payment_method')->nullable();
            $table->dateTime('transaction_date');
            $table->string('reference_no')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_share_transactions');
    }
};
