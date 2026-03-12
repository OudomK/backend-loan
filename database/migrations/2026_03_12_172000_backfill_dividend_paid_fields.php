<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill total_dividend_paid and last_dividend_date for capital shares
 * that already had dividends distributed but these fields were never updated.
 */
return new class extends Migration {
    public function up(): void
    {
        // For each capital share, sum up all paid dividend transactions
        // and set total_dividend_paid = dividends (since they should match)
        // and last_dividend_date = the most recent paid_at from dividend_transactions
        $shares = DB::table('capital_shares')
            ->where('dividends', '>', 0)
            ->where(function ($q) {
                $q->where('total_dividend_paid', 0)
                  ->orWhereNull('total_dividend_paid');
            })
            ->get();

        foreach ($shares as $share) {
            // Get the sum of all paid dividend transactions for this share
            $paidSum = DB::table('dividend_transactions')
                ->where('capital_share_id', $share->id)
                ->where('status', 'Paid')
                ->sum('amount');

            // Get the latest paid_at date
            $lastPaid = DB::table('dividend_transactions')
                ->where('capital_share_id', $share->id)
                ->where('status', 'Paid')
                ->orderByDesc('paid_at')
                ->value('paid_at');

            if ($paidSum > 0) {
                DB::table('capital_shares')
                    ->where('id', $share->id)
                    ->update([
                        'total_dividend_paid' => $paidSum,
                        'last_dividend_date' => $lastPaid ? date('Y-m-d', strtotime($lastPaid)) : now()->toDateString(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Reset backfilled fields
        DB::table('capital_shares')->update([
            'total_dividend_paid' => 0,
            'last_dividend_date' => null,
        ]);
    }
};
