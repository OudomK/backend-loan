<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $transactions = DB::table('repayment_transactions')
            ->where('penalty_paid', '>', 0)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($transactions as $tx) {
            $txDate = Carbon::parse($tx->transaction_date)->toDateString();

            // Prefer an installment due on/before transaction date.
            $targetPayment = DB::table('payments')
                ->where('loan_id', $tx->loan_id)
                ->whereDate('payment_date', '<=', $txDate)
                ->orderBy('payment_date', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            // Fallback to earliest installment for the loan.
            if (!$targetPayment) {
                $targetPayment = DB::table('payments')
                    ->where('loan_id', $tx->loan_id)
                    ->orderBy('payment_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();
            }

            if (!$targetPayment) {
                continue;
            }

            DB::table('payments')
                ->where('id', $targetPayment->id)
                ->update([
                    'penalty_amount' => round(((float) $targetPayment->penalty_amount) + (float) $tx->penalty_paid, 2),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way data backfill. Do not auto-revert.
    }
};

