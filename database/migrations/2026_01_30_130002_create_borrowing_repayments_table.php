<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('borrowing_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrowing_id')->constrained('borrowings')->onDelete('cascade');
            $table->date('payment_date');
            $table->decimal('principal_paid', 15, 2);
            $table->decimal('interest_paid', 15, 2);
            $table->decimal('total_paid', 15, 2);
            $table->string('payment_method')->default('Cash');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowing_repayments');
    }
};
