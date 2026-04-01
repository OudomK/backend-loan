<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\RepaymentTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class MonthlyPerformanceChart extends ChartWidget
{
    protected ?string $heading = 'Disbursement vs Collection';
    protected ?string $description = '12-month comparison in USD';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';
    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $months = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
            $labels[] = now()->subMonths($i)->format('M');
        }

        $exchangeRate = (float) (\App\Models\Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
            ?? \App\Models\Setting::where('key', 'exchange_rate')->value('value')
            ?? 4000);
        $exchangeRate = max(1, $exchangeRate);

        // Fetch disbursements grouped by month and currency
        $disbursementsRaw = Loan::where('status', 'active')
            ->where('start_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE_FORMAT(start_date, "%Y-%m") as month, currency, SUM(amount) as total_amount')
            ->groupBy('month', 'currency')
            ->get();

        $disbursements = collect($months)->mapWithKeys(function ($m) {
            return [$m => 0];
        });

        foreach ($disbursementsRaw as $d) {
            $amount = $d->total_amount;
            if (str_starts_with($d->currency, 'KHR')) {
                $amount = $amount / $exchangeRate;
            }
            $disbursements[$d->month] += $amount;
        }

        // Fetch collections grouped by month and currency
        $collectionsRaw = RepaymentTransaction::where('transaction_date', '>=', now()->subMonths(11)->startOfMonth())
            ->with('loan') // Need loan to check currency
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

        $disbursementData = collect($months)->map(fn($m) => round($disbursements->get($m, 0), 2))->toArray();
        $collectionData = collect($months)->map(fn($m) => round($collections->get($m, 0), 2))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Disbursements ($)',
                    'data' => $disbursementData,
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#f59e0b',
                    'borderRadius' => 8,
                    'maxBarThickness' => 30,
                ],
                [
                    'label' => 'Collections ($)',
                    'data' => $collectionData,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                    'borderRadius' => 8,
                    'maxBarThickness' => 30,
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
                    'position' => 'bottom',
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'x' => [
                    'stacked' => false,
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'stacked' => false,
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
