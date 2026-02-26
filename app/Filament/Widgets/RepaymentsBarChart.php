<?php

namespace App\Filament\Widgets;

use App\Models\RepaymentTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RepaymentsBarChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Repayments';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $months = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
            $labels[] = now()->subMonths($i)->format('M');
        }

        $data = RepaymentTransaction::where('transaction_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as month, SUM(amount_paid) as total')
            ->groupBy('month')
            ->get()
            ->pluck('total', 'month');

        $finalData = collect($months)->map(fn($m) => $data->get($m, 0))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Repayments ($)',
                    'data' => $finalData,
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
