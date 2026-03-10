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
        $months = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
            $labels[] = now()->subMonths($i)->format('M');
        }

        $exchangeRate = (int) (cache()->remember('setting.exchange_rate', 3600, fn () => \App\Models\Setting::where('key', 'exchange_rate')->value('value')) ?? 4000);

        // Aggregate in DB instead of loading all rows (much faster)
        $collectionsRaw = RepaymentTransaction::query()
            ->where('transaction_date', '>=', now()->subMonths(11)->startOfMonth())
            ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id')
            ->selectRaw('DATE_FORMAT(repayment_transactions.transaction_date, "%Y-%m") as month, loans.currency, SUM(repayment_transactions.amount_paid) as total')
            ->groupBy('month', 'loans.currency')
            ->get();

        $collections = collect($months)->mapWithKeys(fn ($m) => [$m => 0])->all();

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
