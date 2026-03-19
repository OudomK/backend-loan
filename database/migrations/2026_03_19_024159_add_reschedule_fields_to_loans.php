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
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('reschedule_fee', 15, 2)->default(0)->after('refinance_fee');
            $table->timestamp('rescheduled_at')->nullable()->after('reschedule_fee');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['reschedule_fee', 'rescheduled_at']);
        });
    }
};
