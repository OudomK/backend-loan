<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class DisbursementLineChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Disbursements';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $months = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
            $labels[] = now()->subMonths($i)->format('M');
        }

        $exchangeRate = (int) (\App\Models\Setting::where('key', 'exchange_rate')->value('value') ?? 4000);

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
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
