<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('written_off_at')->nullable()->after('status');
            $table->string('write_off_reason')->nullable()->after('written_off_at');
            $table->string('classify_wo')->nullable()->after('write_off_reason');
            $table->decimal('write_off_balance', 15, 2)->default(0)->after('classify_wo');
            $table->decimal('recovery_amount', 15, 2)->default(0)->after('write_off_balance');
            $table->date('maturity_date')->nullable()->after('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['written_off_at', 'write_off_reason', 'classify_wo', 'write_off_balance', 'recovery_amount', 'maturity_date']);
        });
    }
};
