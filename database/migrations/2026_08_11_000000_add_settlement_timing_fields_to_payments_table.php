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
        Schema::table('payments', function (Blueprint $table) {
            $table->date('settled_at')->nullable();
            $table->date('settled_due_date')->nullable();
            $table->integer('settled_days_variance')->nullable();
            $table->string('settlement_source', 30)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'settled_at',
                'settled_due_date',
                'settled_days_variance',
                'settlement_source',
            ]);
        });
    }
};
