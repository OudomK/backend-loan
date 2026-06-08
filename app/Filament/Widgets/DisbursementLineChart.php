<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class DisbursementLineChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Disbursement Trend';
    protected ?string $description = 'Last 12 months (USD-normalized)';
    protected static ?int $sort = 2;
    protected ?string $pollingInterval = null;
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $months = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->startOfMonth()->subMonths($i)->format('Y-m');
            $labels[] = now()->startOfMonth()->subMonths($i)->format('M');
        }

        $cachedData = Cache::remember('filament.stats.monthly_performance', 60 * 60, function() {
            app(\App\Services\DashboardStatsService::class)->calculateAndCacheAll();
            return Cache::get('filament.stats.monthly_performance', [
                'disbursements' => [],
                'collections' => []
            ]);
        });

        $disbursements = $cachedData['disbursements'] ?? [];

        $finalData = collect($months)->map(fn($m) => round($disbursements[$m] ?? 0, 2))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Disbursements ($)',
                    'data' => $finalData,
                    'borderColor' => '#10b981', // Emerald 500
                    'pointRadius' => 0,
                    'pointHoverRadius' => 6,
                    'pointBackgroundColor' => '#10b981',
                    'pointBorderColor' => '#fff',
                    'borderWidth' => 4,
                    'tension' => 0.45,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)', // Emerald transparent
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
