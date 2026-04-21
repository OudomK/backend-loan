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
        Schema::table('borrowing_repayments', function (Blueprint $table) {
            $table->string('receipt_no')->nullable()->unique()->after('borrowing_id');
            $table->decimal('penalty_paid', 15, 2)->default(0)->after('interest_paid');
            $table->decimal('balance_after_payment', 15, 2)->after('total_paid');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null')->after('balance_after_payment');
            $table->string('reference_no')->nullable()->after('payment_method');
            $table->string('payment_status')->default('confirmed')->after('reference_no');
            $table->foreignId('schedule_id')->nullable()->constrained('borrowing_schedules')->onDelete('set null')->after('borrowing_id');
        });
    }

    public function down(): void
    {
        Schema::table('borrowing_repayments', function (Blueprint $table) {
            $table->dropForeign(['received_by']);
            $table->dropForeign(['schedule_id']);
            $table->dropColumn(['receipt_no', 'penalty_paid', 'balance_after_payment', 'received_by', 'reference_no', 'payment_status', 'schedule_id']);
        });
    }
};
