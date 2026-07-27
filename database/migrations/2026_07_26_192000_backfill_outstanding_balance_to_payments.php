<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $loans = DB::table('loans')->get();

        foreach ($loans as $loan) {
            $payments = DB::table('payments')
                ->where('loan_id', $loan->id)
                ->whereNull('deleted_at')
                ->orderBy('payment_number', 'asc')
                ->orderBy('payment_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($payments->isEmpty()) {
                continue;
            }

            $currentBalance = (float) $loan->amount;
            if ($currentBalance <= 0) {
                $currentBalance = (float) $payments->sum('principal_amount');
            }

            foreach ($payments as $payment) {
                $currentBalance -= (float) $payment->principal_amount;
                if ($currentBalance < 0.001) {
                    $currentBalance = 0.0;
                }

                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update([
                        'outstanding_balance' => round($currentBalance, 2),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way data backfill.
    }
};
