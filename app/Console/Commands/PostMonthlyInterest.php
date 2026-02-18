<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PostMonthlyInterest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:post-monthly-interest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and post monthly interest for all active saving accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting monthly interest calculation...');

        $accounts = \App\Models\SavingAccount::where('status', 'Active')->get();
        $count = 0;

        foreach ($accounts as $account) {
            if ($account->balance <= 0) {
                continue;
            }

            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($account) {
                    $account->lockForUpdate();
                    $freshAccount = $account->fresh();

                    // Formula: Balance * (Rate / 100) / 12
                    $monthlyInterest = $freshAccount->balance * ($freshAccount->interest_rate / 100) / 12;
                    $monthlyInterest = round($monthlyInterest, 2);

                    if ($monthlyInterest > 0) {
                        // Update balance
                        $freshAccount->increment('balance', $monthlyInterest);

                        // Create transaction record
                        \App\Models\SavingTransaction::create([
                            'saving_account_id' => $freshAccount->id,
                            'transaction_type' => 'Interest',
                            'amount' => $monthlyInterest,
                            'currency' => $freshAccount->currency,
                            'transaction_date' => now(),
                            'description' => 'Monthly Interest',
                        ]);
                    }
                });
                $count++;
            } catch (\Exception $e) {
                $this->error("Failed to process account {$account->account_number}: {$e->getMessage()}");
            }
        }

        $this->info("Successfully processed interest for {$count} accounts.");
    }
}
