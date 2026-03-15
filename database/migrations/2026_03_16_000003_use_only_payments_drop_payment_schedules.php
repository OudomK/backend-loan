<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schedule lives only in payments table. Drop payment_schedule_id and payment_schedules table.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'payment_schedule_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['payment_schedule_id']);
                $table->dropColumn('payment_schedule_id');
            });
        }
        Schema::dropIfExists('payment_schedules');
    }

    public function down(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->decimal('principal_due', 15, 2)->default(0);
            $table->decimal('interest_due', 15, 2)->default(0);
            $table->decimal('penalty_due', 15, 2)->default(0);
            $table->decimal('total_due', 15, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
            $table->timestamps();
            $table->index(['loan_id', 'installment_number']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_schedule_id')->nullable()->after('loan_id')
                ->constrained('payment_schedules')->onDelete('set null');
        });
    }
};
