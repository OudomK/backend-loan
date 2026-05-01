<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class DisbursementLineChart extends ChartWidget
{
    use HasWidgetShield;

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
                    'borderColor' => '#10b981', // Emerald 500
                    'pointRadius' => 0,
                    'pointHoverRadius' => 6,
                    'pointBackgroundColor' => '#10b981',
                    'pointBorderColor' => '#fff',
                    'borderWidth' => 4,
                    'tension' => 0.45,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)', // Emerald transparent
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
