<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class ParAgingChart extends ChartWidget
{
    protected ?string $heading = 'PAR Aging Breakdown';
    protected ?string $description = 'Portfolio at risk by aging bucket (loan count)';
    protected static ?int $sort = 7;
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $ttl = 60;

        $buckets = Cache::remember('filament.chart.par_aging', $ttl, function () {
            return [
                'Current'  => Loan::where('status', 'active')->where(function ($q) {
                    $q->where('aging', 0)->orWhereNull('aging');
                })->count(),
                '1–30 days'  => Loan::where('status', 'active')->whereBetween('aging', [1, 30])->count(),
                '31–60 days' => Loan::where('status', 'active')->whereBetween('aging', [31, 60])->count(),
                '61–90 days' => Loan::where('status', 'active')->whereBetween('aging', [61, 90])->count(),
                '90+ days'   => Loan::where('status', 'active')->where('aging', '>', 90)->count(),
            ];
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
