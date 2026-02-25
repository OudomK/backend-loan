<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class DisbursementLineChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Disbursements';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // Fallback if Trend is not installed, or use simple query
        $data = Loan::selectRaw('SUM(amount) as total, MONTH(start_date) as month')
            ->whereYear('start_date', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Disbursements ($)',
                    'data' => $data->map(fn($value) => $value->total),
                    'borderColor' => '#f59e0b',
                ],
            ],
            'labels' => $data->map(fn($value) => date("M", mktime(0, 0, 0, $value->month, 10))),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
