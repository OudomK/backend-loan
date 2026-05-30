<?php

namespace App\Filament\Widgets;

use App\Models\RepaymentTransaction;
use Filament\Widgets\ChartWidget;

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

        $exchangeRate = (float) cache()->remember('setting.exchange_rate_khr_to_usd', 3600, function () {
            $rate = \App\Models\Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
                ?? \App\Models\Setting::where('key', 'exchange_rate')->value('value')
                ?? 4000;

            return max(1, (float) $rate);
        });

        // Aggregate in DB instead of loading all rows (much faster)
        $collectionsRaw = RepaymentTransaction::query()
            ->where('transaction_date', '>=', now()->startOfMonth()->subMonths(11))
            ->whereNull('repayment_transactions.deleted_at')
            ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id')
            ->selectRaw('DATE_FORMAT(repayment_transactions.transaction_date, "%Y-%m") as month, loans.currency, SUM(repayment_transactions.amount_paid) as total')
            ->groupBy('month', 'loans.currency')
            ->get();

        $collections = array_fill_keys($months, 0);

        foreach ($collectionsRaw as $d) {
            $amount = (float) $d->total;
            if (str_starts_with($d->currency ?? '', 'KHR')) {
                $amount = $amount / $exchangeRate;
            }
            $collections[$d->month] = ($collections[$d->month] ?? 0) + $amount;
        }

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
