<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $borrowers = DB::table('loans')->select('borrower_id')->distinct()->get();

        foreach ($borrowers as $borrower) {
            $loans = DB::table('loans')
                ->where('borrower_id', $borrower->borrower_id)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($loans as $index => $loan) {
                DB::table('loans')
                    ->where('id', $loan->id)
                    ->update(['loan_cycle' => $index + 1]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to undo backfill
    }
};
