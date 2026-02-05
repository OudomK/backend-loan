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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('duration_months');
            $table->decimal('monthly_payment', 15, 2);
            $table->date('start_date');
            $table->enum('status', ['pending', 'active', 'completed', 'paid_off'])->default('pending');
            $table->foreignId('co_borrower_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('guarantor_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
