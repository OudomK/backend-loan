<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class DisbursementLineChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Disbursements';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $months = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
            $labels[] = now()->subMonths($i)->format('M');
        }

        $data = Loan::where('status', 'active')
            ->where('start_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE_FORMAT(start_date, "%Y-%m") as month, SUM(amount) as total')
            ->groupBy('month')
            ->get()
            ->pluck('total', 'month');

        $finalData = collect($months)->map(fn($m) => $data->get($m, 0))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Disbursements ($)',
                    'data' => $finalData,
                    'borderColor' => '#f59e0b',
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
