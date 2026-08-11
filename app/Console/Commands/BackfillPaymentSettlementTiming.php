<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentSettlementTimingService;
use Illuminate\Console\Command;

class BackfillPaymentSettlementTiming extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:backfill-settlement-timing
        {--write : Persist resolved snapshots; otherwise run as a dry run}
        {--force-recompute : Re-evaluate rows that already have a snapshot}
        {--include-updated-at-fallback : Use updated_at only when no transaction evidence exists}
        {--chunk=500 : Number of payment rows processed per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely backfill immutable row-level payment settlement timing snapshots';

    public function handle(PaymentSettlementTimingService $timingService): int
    {
        $write = (bool) $this->option('write');
        $forceRecompute = (bool) $this->option('force-recompute');
        $allowUpdatedAtFallback = (bool) $this->option('include-updated-at-fallback');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $stats = [
            'scanned' => 0,
            'resolved' => 0,
            'unsettled' => 0,
            'unresolved' => 0,
            'written' => 0,
        ];
        $sources = [];

        $query = Payment::withTrashed()->orderBy('id');
        if (! $forceRecompute) {
            $query->whereNull('settled_days_variance');
        }

        $query->chunkById($chunkSize, function ($payments) use (
            $timingService,
            $write,
            $allowUpdatedAtFallback,
            &$stats,
            &$sources
        ): void {
            foreach ($payments as $payment) {
                $stats['scanned']++;
                $result = $timingService->resolve($payment, $allowUpdatedAtFallback);
                $stats[$result['status']]++;

                if ($result['status'] !== 'resolved') {
                    continue;
                }

                $source = $result['settlement_source'];
                $sources[$source] = ($sources[$source] ?? 0) + 1;

                if (! $write) {
                    continue;
                }

                $payment->forceFill([
                    'settled_at' => $result['settled_at'],
                    'settled_due_date' => $result['settled_due_date'],
                    'settled_days_variance' => $result['settled_days_variance'],
                    'settlement_source' => $source,
                ])->saveQuietly();
                $stats['written']++;
            }
        }, 'id');

        ksort($sources);

        $this->newLine();
        $this->info($write ? 'Settlement timing backfill completed.' : 'Settlement timing dry run completed; no rows were changed.');
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric) => [$metric, $count])->values()->all()
        );

        if ($sources !== []) {
            $this->table(
                ['Resolved source', 'Count'],
                collect($sources)->map(fn (int $count, string $source) => [$source, $count])->values()->all()
            );
        }

        if ($stats['unresolved'] > 0 && ! $allowUpdatedAtFallback) {
            $this->warn(
                'Some fully paid rows have no reliable transaction evidence. They remain null; '
                .'review them or rerun with --include-updated-at-fallback to mark the lower-confidence source explicitly.'
            );
        }

        return self::SUCCESS;
    }
}
