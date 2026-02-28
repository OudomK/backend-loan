<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // SQLite doesn't support modifying ENUMs directly easily, 
        // but since this is usually MySQL or Postgres in production:
        // For MySQL:
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE capital_share_transactions MODIFY COLUMN transaction_type ENUM('Initial', 'Deposit', 'Withdrawal', 'Dividend', 'Repayment') NOT NULL");
        } else {
            // For others (Postgres/SQLite), we might need to recreate or ignore if it's just a string check.
            // In Laravel, the enum is often just a string check at the app level if not strict.
            // But to be safe for this project's structure:
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE capital_share_transactions MODIFY COLUMN transaction_type ENUM('Initial', 'Deposit', 'Withdrawal', 'Dividend') NOT NULL");
        }
    }
};
