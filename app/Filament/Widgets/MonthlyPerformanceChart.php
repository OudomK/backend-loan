<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\RepaymentTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class MonthlyPerformanceChart extends ChartWidget
{
    protected ?string $heading = 'Performance: Disbursements vs Collections';
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';
    protected ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $months = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
            $labels[] = now()->subMonths($i)->format('M');
        }

        $disbursements = Loan::where('status', 'active')
            ->where('start_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE_FORMAT(start_date, "%Y-%m") as month, SUM(amount) as total')
            ->groupBy('month')
            ->get()
            ->pluck('total', 'month');

        $collections = RepaymentTransaction::where('transaction_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as month, SUM(amount_paid) as total')
            ->groupBy('month')
            ->get()
            ->pluck('total', 'month');

        $disbursementData = collect($months)->map(fn($m) => $disbursements->get($m, 0))->toArray();
        $collectionData = collect($months)->map(fn($m) => $collections->get($m, 0))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Disbursements ($)',
                    'data' => $disbursementData,
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#f59e0b',
                ],
                [
                    'label' => 'Collections ($)',
                    'data' => $collectionData,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
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
