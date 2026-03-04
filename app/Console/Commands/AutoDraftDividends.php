<?php

namespace App\Console\Commands;

use App\Models\CapitalShare;
use App\Models\Dividend;
use App\Models\DividendSchedule;
use App\Models\DividendTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AutoDraftDividends extends Command
{
    protected $signature = 'app:auto-draft-dividends';
    protected $description = 'Auto-create Draft dividend declarations based on active schedules';

    public function handle(): void
    {
        $this->info('Checking dividend schedules...');

        /** @var \Illuminate\Database\Eloquent\Collection<int, DividendSchedule> $schedules */
        $schedules = DividendSchedule::where('is_active', true)->get();

        if ($schedules->isEmpty()) {
            $this->info('No active dividend schedules found.');
            return;
        }

        $created = 0;

        foreach ($schedules as $schedule) {
            if (!$this->isDue($schedule)) {
                continue;
            }

            try {
                DB::transaction(function () use ($schedule, &$created) {
                    // Get all active Real Capital shares for this currency
                    $shares = CapitalShare::where('currency', $schedule->currency)
                        ->where('status', 'Active')
                        ->where('category', 'Real Capital')
                        ->get();

                    $totalSharesCount = $shares->sum('share_qty');

                    if ($totalSharesCount == 0) {
                        $this->warn("No active shares for currency: {$schedule->currency}. Skipping.");
                        return;
                    }

                    // Calculate amounts
                    if ($schedule->type === 'total') {
                        $totalAmount = (float) $schedule->amount;
                        $perShare = $totalAmount / $totalSharesCount;
                    } else {
                        $perShare = (float) $schedule->amount;
                        $totalAmount = $perShare * $totalSharesCount;
                    }

                    // Create Draft dividend declaration
                    $dividend = Dividend::create([
                        'total_amount' => round($totalAmount, 2),
                        'dividend_per_share' => round($perShare, 4),
                        'currency' => $schedule->currency,
                        'total_shares_count' => $totalSharesCount,
                        'declared_date' => now()->toDateString(),
                        'status' => 'Draft',
                    ]);

                    // Create Pending transactions for each shareholder
                    foreach ($shares as $share) {
                        DividendTransaction::create([
                            'dividend_id' => $dividend->id,
                            'capital_share_id' => $share->id,
                            'amount' => round($share->share_qty * $perShare, 2),
                            'currency' => $schedule->currency,
                            'status' => 'Pending',
                        ]);
                    }

                    // Update last_run_at
                    $schedule->update(['last_run_at' => now()]);

                    $created++;
                    $this->info("Created Draft Dividend #DIV-{$dividend->id} for {$schedule->currency} ({$schedule->frequency}).");
                });
            } catch (\Exception $e) {
                $this->error("Failed for schedule #{$schedule->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Created {$created} draft dividend(s).");
    }

    /**
     * Determine if a schedule should run today.
     */
    private function isDue(DividendSchedule $schedule): bool
    {
        $today = Carbon::today();
        $lastRun = $schedule->last_run_at ? Carbon::parse($schedule->last_run_at) : null;
        $dayMatch = $today->day === (int) $schedule->day_of_month;

        if (!$dayMatch) {
            return false;
        }

        if ($lastRun === null) {
            return true; // Never run before
        }

        return match ($schedule->frequency) {
            'monthly' => $lastRun->lt($today->copy()->startOfMonth()),
            'quarterly' => $lastRun->lt($today->copy()->startOfQuarter()),
            'yearly' => $lastRun->lt($today->copy()->startOfYear()),
            default => false,
        };
    }
}
