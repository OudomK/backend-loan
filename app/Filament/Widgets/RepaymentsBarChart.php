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

        $exchangeRate = (int) (\App\Models\Setting::where('key', 'exchange_rate')->value('value') ?? 4000);

        $collectionsRaw = RepaymentTransaction::where('transaction_date', '>=', now()->subMonths(11)->startOfMonth())
            ->with('loan')
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->transaction_date)->format('Y-m');
            });

        $collections = collect($months)->mapWithKeys(function ($m) {
            return [$m => 0];
        });

        foreach ($collectionsRaw as $month => $transactions) {
            $totalForMonth = 0;
            foreach ($transactions as $t) {
                $amount = $t->amount_paid;
                if ($t->loan && str_starts_with($t->loan->currency, 'KHR')) {
                    $amount = $amount / $exchangeRate;
                }
                $totalForMonth += $amount;
            }
            $collections[$month] = $totalForMonth;
        }

        $finalData = collect($months)->map(fn($m) => round($collections->get($m, 0), 2))->toArray();

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
