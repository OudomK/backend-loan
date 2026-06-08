<?php

namespace App\Filament\Widgets;

use App\Models\RepaymentTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class RepaymentsBarChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Collection Trend';
    protected ?string $description = 'Monthly repayments (USD-normalized)';
    protected static ?int $sort = 3;
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

        $collections = $cachedData['collections'] ?? [];

        $finalData = collect($months)->map(fn ($m) => round($collections[$m] ?? 0, 2))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Repayments ($)',
                    'data' => $finalData,
                    'backgroundColor' => '#4f46e5', // Indigo 600
                    'borderRadius' => 6,
                    'borderWidth' => 0,
                    'borderSkipped' => false,
                    'maxBarThickness' => 32,
                    'hoverBackgroundColor' => '#6366f1', // Indigo 500
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
        return 'bar';
    }
}
