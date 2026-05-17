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
        Schema::create('revenues', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('revenue_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 20, 2);
            $table->string('currency', 10)->default('USD');
            $table->date('transaction_date');
            $table->string('reference_no')->unique()->nullable();
            $table->string('payment_method')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('completed');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenues');
    }
};
