<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lenders', function (Blueprint $table) {
            $table->id();
            $table->string('lender_code')->unique();
            $table->string('name');
            $table->string('lender_type'); // Individual, Local Bank & FI, Local Lender
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lenders');
    }
};
