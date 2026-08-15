<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'completed', 'paid_off', 'refinanced', 'written_off', 'rejected') DEFAULT 'pending'");

            return;
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE loans DROP CONSTRAINT loans_status_check");
            DB::statement("ALTER TABLE loans ADD CONSTRAINT loans_status_check CHECK (status IN ('pending', 'active', 'completed', 'paid_off', 'refinanced', 'written_off', 'rejected'))");

            return;
        }

        Schema::table('loans', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'active', 'completed', 'paid_off', 'refinanced', 'written_off', 'rejected'])
                ->default('pending')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'completed', 'paid_off') DEFAULT 'pending'");

            return;
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE loans DROP CONSTRAINT loans_status_check");
            DB::statement("ALTER TABLE loans ADD CONSTRAINT loans_status_check CHECK (status IN ('pending', 'active', 'completed', 'paid_off'))");

            return;
        }

        Schema::table('loans', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'active', 'completed', 'paid_off'])
                ->default('pending')
                ->change();
        });
    }
};
