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
        Schema::create('saving_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saving_account_id')->constrained('saving_accounts')->onDelete('cascade');
            $table->enum('transaction_type', ['Deposit', 'Withdrawal', 'Interest']);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->dateTime('transaction_date');
            $table->string('reference_no')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saving_transactions');
    }
};
