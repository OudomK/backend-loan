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
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','active','completed','paid_off','refinanced','rescheduled','written_off','rejected') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // It's generally risky to drop an enum value, so we leave it or revert back if needed.
        // \Illuminate\Support\Facades\DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','active','completed','paid_off','refinanced','written_off','rejected') NOT NULL DEFAULT 'pending'");
    }
};
