<?php

namespace App\Jobs;

use App\Services\DashboardStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateDashboardStatsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     * Setting this ensures we don't recalculate stats repeatedly if many events fire within a short time.
     */
    public $uniqueFor = 10;

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'dashboard_stats';
    }

    /**
     * Execute the job.
     */
    public function handle(DashboardStatsService $service): void
    {
        $service->calculateAndCacheAll();
    }
}
