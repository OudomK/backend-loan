<?php

namespace App\Filament\Widgets;

use App\Models\RepaymentTransaction;
use Filament\Widgets\ChartWidget;

class RepaymentsBarChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Repayments';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = RepaymentTransaction::selectRaw('SUM(amount_paid) as total, MONTH(transaction_date) as month')
            ->whereYear('transaction_date', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Repayments ($)',
                    'data' => $data->map(fn($value) => $value->total),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $data->map(fn($value) => date("M", mktime(0, 0, 0, $value->month, 10))),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
