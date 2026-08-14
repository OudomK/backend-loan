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
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'pending_check', 'pending_verify', 'pending_approval', 'active', 'completed', 'paid_off', 'refinanced', 'rescheduled', 'written_off', 'rejected') NOT NULL DEFAULT 'pending_check'");

            return;
        }

        Schema::table('loans', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'pending_check', 'pending_verify', 'pending_approval', 'active', 'completed', 'paid_off', 'refinanced', 'rescheduled', 'written_off', 'rejected'])
                ->default('pending_check')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'completed', 'paid_off', 'refinanced', 'rescheduled', 'written_off', 'rejected') NOT NULL DEFAULT 'pending'");

            return;
        }

        Schema::table('loans', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'active', 'completed', 'paid_off', 'refinanced', 'rescheduled', 'written_off', 'rejected'])
                ->default('pending')
                ->change();
        });
    }
};
