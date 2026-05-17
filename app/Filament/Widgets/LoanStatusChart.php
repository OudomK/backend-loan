<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class LoanStatusChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Loan Status Distribution';
    protected ?string $description = 'Breakdown of all loans by status';
    protected static ?int $sort = 6;
    protected ?string $pollingInterval = null;
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $ttl = 60;

        $statuses = Cache::remember('filament.chart.loan_status', $ttl, function () {
            return [
                'active'    => Loan::where('status', 'active')->count(),
                'pending'   => Loan::where('status', 'pending')->count(),
                'completed' => Loan::where('status', 'completed')->count(),
                'written_off' => Loan::whereNotNull('written_off_at')->count(),
                'rejected'  => Loan::where('status', 'rejected')->count(),
            ];
        });

        // Filter out zero values for cleaner chart
        $filtered = array_filter($statuses, fn ($v) => $v > 0);

        $colorMap = [
            'active'      => '#10b981', // Emerald
            'pending'     => '#f59e0b', // Amber
            'completed'   => '#6366f1', // Indigo
            'written_off' => '#ef4444', // Red
            'rejected'    => '#6b7280', // Gray
        ];

        $labels = array_map(fn ($k) => ucfirst(str_replace('_', ' ', $k)), array_keys($filtered));
        $colors = array_map(fn ($k) => $colorMap[$k] ?? '#94a3b8', array_keys($filtered));

        return [
            'datasets' => [
                [
                    'data' => array_values($filtered),
                    'backgroundColor' => array_values($colors),
                    'borderColor' => 'transparent',
                    'borderWidth' => 0,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => array_values($labels),
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 16,
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                    ],
                ],
            ],
            'cutout' => '65%',
            'maintainAspectRatio' => false,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
