<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->boolean('schedule_needs_recalculation')->default(false)->after('rejection_reason');
            $table->timestamp('schedule_recalculated_at')->nullable()->after('schedule_needs_recalculation');
            $table->foreignId('schedule_recalculated_by')
                ->nullable()
                ->after('schedule_recalculated_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('schedule_recalculated_by');
            $table->dropColumn([
                'schedule_needs_recalculation',
                'schedule_recalculated_at',
            ]);
        });
    }
};
