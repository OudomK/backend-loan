<?php

namespace App\Console\Commands;

use App\Services\DashboardStatsService;
use Illuminate\Console\Command;

class CacheDashboardStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cache-dashboard-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-calculate and cache the dashboard statistics for Filament widgets.';

    /**
     * Execute the console command.
     */
    public function handle(DashboardStatsService $service)
    {
        $this->info('Starting dashboard stats caching...');
        
        $startTime = microtime(true);
        $service->calculateAndCacheAll();
        $endTime = microtime(true);

        $this->info('Dashboard stats cached successfully in ' . round($endTime - $startTime, 2) . ' seconds.');
    }
}
