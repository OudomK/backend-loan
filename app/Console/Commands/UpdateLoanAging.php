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
        $updatedCount = 0;

        foreach ($loans as $loan) {
            // Keep scheduler behavior identical to repayment processing.
            $loan->updateAging();
            $updatedCount++;

            if ($updatedCount % 50 === 0) {
                $this->line("Processed {$updatedCount} loans...");
            }
        }

        $this->info("Completed! Updated aging for {$updatedCount} loans.");
    }
}
