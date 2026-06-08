<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class ParAgingChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'PAR Aging Breakdown';
    protected ?string $description = 'Portfolio at risk by aging bucket (loan count)';
    protected static ?int $sort = 7;
    protected ?string $pollingInterval = null;
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $buckets = Cache::remember('filament.stats.par_aging_buckets', 60 * 60, function() {
            app(\App\Services\DashboardStatsService::class)->calculateAndCacheAll();
            return Cache::get('filament.stats.par_aging_buckets', [
                'Current'    => 0,
                '1–30 days'  => 0,
                '31–60 days' => 0,
                '61–90 days' => 0,
                '90+ days'   => 0,
            ]);
        });

        return [
            'datasets' => [
                [
                    'label' => 'Loans',
                    'data' => array_values($buckets),
                    'backgroundColor' => [
                        '#10b981', // Current → Emerald
                        '#f59e0b', // 1-30 → Amber
                        '#f97316', // 31-60 → Orange
                        '#ef4444', // 61-90 → Red
                        '#991b1b', // 90+ → Dark Red
                    ],
                    'borderRadius' => 6,
                    'borderWidth' => 0,
                    'borderSkipped' => false,
                    'maxBarThickness' => 40,
                ],
            ],
            'labels' => array_keys($buckets),
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => true,
                    ],
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
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
