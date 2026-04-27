<?php

namespace App\Console\Commands;

use App\Models\CapitalShare;
use App\Models\Dividend;
use App\Models\DividendSchedule;
use App\Models\DividendTransaction;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AutoDraftDividends extends Command
{
    protected $signature = 'app:auto-draft-dividends';
    protected $description = 'Auto-create Draft dividend declarations based on active schedules';

    private function getBoolSetting(string $key, bool $default = false): bool
    {
        $raw = Setting::where('key', $key)->value('value');
        if ($raw === null) {
            return $default;
        }

        $normalized = strtolower(trim((string) $raw));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function isDividendTaxEnabled(): bool
    {
        return $this->getBoolSetting('enable_dividend_tax', false);
    }

    private function getDividendTaxRate(): float
    {
        $raw = Setting::where('key', 'dividend_tax_rate')->value('value');
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        $rate = (float) $raw;
        if ($rate < 0) {
            return 0.0;
        }
        if ($rate > 100) {
            return 100.0;
        }

        return $rate;
    }

    private function calculateScheduledTaxAmount(float $totalAmount): float
    {
        if (!$this->isDividendTaxEnabled()) {
            return 0.0;
        }

        // Scheduled drafts have no manual tax entry step, so use the configured rate.
        $rate = $this->getDividendTaxRate();
        if ($rate <= 0) {
            return 0.0;
        }

        return round($totalAmount * ($rate / 100), 2);
    }

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

                    $totalAmount = round($totalAmount, 2);
                    $perShare = round($perShare, 4);
                    $taxAmount = $this->calculateScheduledTaxAmount($totalAmount);
                    $netAmount = round($totalAmount - $taxAmount, 2);

                    // Create Draft dividend declaration
                    $dividend = Dividend::create([
                        'total_amount' => $totalAmount,
                        'dividend_per_share' => $perShare,
                        'currency' => $schedule->currency,
                        'distribution_basis' => $schedule->type,
                        'total_shares_count' => $totalSharesCount,
                        'declared_date' => now()->toDateString(),
                        'payment_date' => now()->toDateString(),
                        'declared_by' => null,
                        'notes' => 'Auto-created from dividend schedule #' . $schedule->id,
                        'tax_amount' => $taxAmount,
                        'net_amount' => $netAmount,
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
