<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class ParAgingChart extends ChartWidget
{
    protected ?string $heading = 'PAR Aging Breakdown';
    protected ?string $description = 'Portfolio at risk by aging bucket (loan count)';
    protected static ?int $sort = 7;
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $today = now()->toDateString();

        $loans = Loan::where('status', 'active')
            ->select('id')
            ->addSelect([
                'real_aging' => \App\Models\Payment::selectRaw('DATEDIFF(?, MIN(payment_date))', [$today])
                    ->whereColumn('loan_id', 'loans.id')
                    ->where('payment_date', '<', $today)
                    ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
            ])
            ->get();

        $buckets = [
            'Current'    => 0,
            '1–30 days'  => 0,
            '31–60 days' => 0,
            '61–90 days' => 0,
            '90+ days'   => 0,
        ];

        foreach ($loans as $loan) {
            $aging = $loan->real_aging ?? 0;

            if ($aging <= 0) {
                $buckets['Current']++;
            } elseif ($aging <= 30) {
                $buckets['1–30 days']++;
            } elseif ($aging <= 60) {
                $buckets['31–60 days']++;
            } elseif ($aging <= 90) {
                $buckets['61–90 days']++;
            } else {
                $buckets['90+ days']++;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Loans',
                    'data' => array_values($buckets),
                    'backgroundColor' => [
                        '#10b981', // Current → Emerald
                        '#f59e0b', // 1-30 → Amber
                        '#f97316', // 31-60 → Orange
                        '#ef4444', // 61-90 → Red
                        '#991b1b', // 90+ → Dark Red
                    ],
                    'borderRadius' => 6,
                    'borderWidth' => 0,
                    'borderSkipped' => false,
                    'maxBarThickness' => 40,
                ],
            ],
            'labels' => array_keys($buckets),
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => true,
                    ],
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
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
