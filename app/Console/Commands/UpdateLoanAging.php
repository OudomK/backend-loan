<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateLoanAging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:update-aging';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates and updates the aging (Days Past Due) for all active and written-off loans';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting loan aging update...');

        $loans = \App\Models\Loan::whereIn('status', ['active', 'written_off'])->get();
        $today = \Carbon\Carbon::today();
        $updatedCount = 0;

        foreach ($loans as $loan) {
            $aging = 0;

            // Find earliest unpaid installment that is past due
            $earliestArrear = \App\Models\Payment::where('loan_id', $loan->id)
                ->where('payment_date', '<', $today->toDateString())
                ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
                ->orderBy('payment_date', 'asc')
                ->first();

            if ($earliestArrear) {
                $earliestDate = \Carbon\Carbon::parse($earliestArrear->payment_date)->startOfDay();
                // Use abs() because diffInDays might return negative values in some Carbon versions
                $aging = (int) abs($today->diffInDays($earliestDate, false));
            }

            // Update the loan record
            $loan->update(['aging' => $aging]);
            $updatedCount++;

            if ($updatedCount % 50 === 0) {
                $this->line("Processed {$updatedCount} loans...");
            }
        }

        $this->info("Completed! Updated aging for {$updatedCount} loans.");
    }
}
