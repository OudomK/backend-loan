<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add total_paid to loans table = sum of payments.total_paid for that loan.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('total_paid', 15, 2)->default(0)->after('amount');
        });

        // Backfill: total_paid = SUM(payments.total_paid) per loan
        $loanIds = DB::table('loans')->pluck('id');
        foreach ($loanIds as $id) {
            $sum = DB::table('payments')->where('loan_id', $id)->sum('total_paid');
            DB::table('loans')->where('id', $id)->update(['total_paid' => $sum ?? 0]);
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('total_paid');
        });
    }
};
