<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create loan_approvals table to track each approval action
        Schema::create('loan_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action'); // submitted, checked, verified, approved, rejected
            $table->string('from_status')->nullable(); // status before action
            $table->string('to_status'); // status after action
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'created_at']);
        });

        // 2. Add approval tracking fields to loans table
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable()->after('payment_qr_id');
            $table->unsignedBigInteger('checked_by')->nullable()->after('submitted_by');
            $table->unsignedBigInteger('verified_by')->nullable()->after('checked_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('verified_by');
            $table->timestamp('checked_at')->nullable()->after('approved_by');
            $table->timestamp('verified_at')->nullable()->after('checked_at');
            $table->timestamp('approved_at')->nullable()->after('verified_at');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_approvals');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'submitted_by',
                'checked_by',
                'verified_by',
                'approved_by',
                'checked_at',
                'verified_at',
                'approved_at',
                'rejection_reason',
            ]);
        });
    }
};
