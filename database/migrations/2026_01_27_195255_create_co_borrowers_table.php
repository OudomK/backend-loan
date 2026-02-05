<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('co_borrowers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender')->nullable();
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

    public function down(): void
    {
        Schema::dropIfExists('co_borrowers');
    }
};
