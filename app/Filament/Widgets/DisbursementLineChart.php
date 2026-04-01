<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class DisbursementLineChart extends ChartWidget
{
    protected ?string $heading = 'Disbursement Trend';
    protected ?string $description = 'Last 12 months (USD-normalized)';
    protected static ?int $sort = 2;
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $months = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
            $labels[] = now()->subMonths($i)->format('M');
        }

        $exchangeRate = (float) cache()->remember('setting.exchange_rate_khr_to_usd', 3600, function () {
            $rate = \App\Models\Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
                ?? \App\Models\Setting::where('key', 'exchange_rate')->value('value')
                ?? 4000;

            return max(1, (float) $rate);
        });

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

        $finalData = collect($months)->map(fn($m) => round($disbursements->get($m, 0), 2))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Disbursements ($)',
                    'data' => $finalData,
                    'borderColor' => '#f59e0b',
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'pointBackgroundColor' => '#f59e0b',
                    'pointBorderColor' => '#f59e0b',
                    'borderWidth' => 3,
                    'tension' => 0.35,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
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
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
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
        return 'line';
    }
}
