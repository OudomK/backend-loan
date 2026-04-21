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
        Schema::table('borrowing_schedules', function (Blueprint $table) {
            $table->decimal('penalty_paid', 15, 2)->default(0)->after('interest_paid');
            $table->date('paid_date')->nullable()->after('status');
            $table->date('last_payment_date')->nullable()->after('paid_date');
            $table->string('note')->nullable()->after('last_payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowing_schedules', function (Blueprint $table) {
            $table->dropColumn(['penalty_paid', 'paid_date', 'last_payment_date', 'note']);
        });
    }
};
