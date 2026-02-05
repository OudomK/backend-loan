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
        Schema::create('saving_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('borrowers')->onDelete('cascade');
            $table->string('account_number')->unique();
            $table->enum('account_type', ['Daily Saving', 'Goal Saving', 'Fixed Deposit']);
            $table->string('currency', 3)->default('USD');
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('term')->nullable();
            $table->date('maturity_date')->nullable();
            $table->enum('status', ['Active', 'Dormant', 'Closed'])->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saving_accounts');
    }
};
