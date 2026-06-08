<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\RepaymentTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class MonthlyPerformanceChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Disbursement vs Collection';
    protected ?string $description = '12-month comparison in USD';
    protected static ?int $sort = 4;
    protected ?string $pollingInterval = null;
    protected int|string|array $columnSpan = 'full';
    protected ?string $maxHeight = '320px';

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
        $collections = $cachedData['collections'] ?? [];

        $disbursementData = collect($months)->map(fn($m) => round($disbursements[$m] ?? 0, 2))->toArray();
        $collectionData = collect($months)->map(fn($m) => round($collections[$m] ?? 0, 2))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Disbursements ($)',
                    'data' => $disbursementData,
                    'backgroundColor' => '#10b981', // Emerald 500
                    'borderRadius' => 6,
                    'borderWidth' => 0,
                    'maxBarThickness' => 30,
                    'hoverBackgroundColor' => '#059669', // Emerald 600
                ],
                [
                    'label' => 'Collections ($)',
                    'data' => $collectionData,
                    'backgroundColor' => '#4f46e5', // Indigo 600
                    'borderRadius' => 6,
                    'borderWidth' => 0,
                    'maxBarThickness' => 30,
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
                    'position' => 'bottom',
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'x' => [
                    'stacked' => false,
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'stacked' => false,
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
