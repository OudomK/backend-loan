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
        Schema::create('savers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender')->nullable();
            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->nullable();
            $table->date('dob')->nullable();
            $table->integer('age')->nullable();
            $table->string('phone');
            $table->string('id_type')->nullable();
            $table->string('id_number')->unique();
            $table->date('id_expiry')->nullable();
            $table->string('occupation')->nullable();
            $table->string('village')->nullable();
            $table->string('commune')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('photo')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savers');
    }
};
