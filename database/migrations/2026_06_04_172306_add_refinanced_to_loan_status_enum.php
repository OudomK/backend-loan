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
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'completed', 'paid_off', 'refinanced', 'written_off', 'rejected') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'completed', 'paid_off') DEFAULT 'pending'");
    }
};
