<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dividend_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 20)->default('USD');
            $table->enum('type', ['per_share', 'total'])->default('per_share');
            $table->decimal('amount', 15, 4)->default(0);
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->unsignedTinyInteger('day_of_month')->default(1); // 1-28
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividend_schedules');
    }
};
