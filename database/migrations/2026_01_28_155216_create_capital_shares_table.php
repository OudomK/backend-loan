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
        Schema::create('capital_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('borrowers')->onDelete('cascade');
            $table->string('holder_id')->unique();
            $table->string('certificate_no')->unique();
            $table->integer('share_qty');
            $table->decimal('par_value', 15, 2);
            $table->decimal('total_capital', 15, 2);
            $table->date('purchase_date');
            $table->enum('status', ['Active', 'Withdrawn'])->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capital_shares');
    }
};
