<?php

namespace App\Observers;

use App\Jobs\UpdateDashboardStatsJob;

class DashboardStatsObserver
{
    /**
     * Handle the model "saved" event.
     */
    public function saved($model): void
    {
        $this->dispatchJob();
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted($model): void
    {
        $this->dispatchJob();
    }

    /**
     * Handle the model "restored" event.
     */
    public function restored($model): void
    {
        $this->dispatchJob();
    }

    /**
     * Handle the model "force deleted" event.
     */
    public function forceDeleted($model): void
    {
        $this->dispatchJob();
    }

    private function dispatchJob(): void
    {
        UpdateDashboardStatsJob::dispatch();
    }
}
