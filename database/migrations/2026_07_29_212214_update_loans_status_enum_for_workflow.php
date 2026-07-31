<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alter the ENUM column to include the new workflow statuses
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'pending_check', 'pending_verify', 'pending_approval', 'active', 'completed', 'paid_off', 'refinanced', 'rescheduled', 'written_off', 'rejected') NOT NULL DEFAULT 'pending_check'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to the old ENUM statuses (this might fail if there are loans with the new statuses)
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'completed', 'paid_off', 'refinanced', 'rescheduled', 'written_off', 'rejected') NOT NULL DEFAULT 'pending'");
    }
};
