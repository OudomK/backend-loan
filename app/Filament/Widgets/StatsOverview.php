<?php

namespace App\Filament\Widgets;

use App\Models\Borrower;
use App\Models\Borrowing;
use App\Models\Loan;
use App\Models\RepaymentTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected ?string $heading = 'Executive Snapshot';
    protected ?string $description = 'Real-time portfolio, disbursement, and collection highlights.';

    protected function getStats(): array
    {
        $ttl = 60; // 1 minute cache
        $disbursementTrend = Cache::remember('filament.stats.trend.disbursements', $ttl, fn () => $this->buildMonthlySeries(
            Loan::query()->where('status', 'active'),
            'start_date',
            'amount',
        ));
        $collectionTrend = Cache::remember('filament.stats.trend.collections', $ttl, fn () => $this->buildMonthlySeries(
            RepaymentTransaction::query(),
            'transaction_date',
            'amount_paid',
        ));

        return [
            Stat::make('Pending Approvals', Cache::remember('filament.stats.pending_loans', $ttl, fn () => Loan::where('status', 'pending')->count()))
                ->description('Loan applications waiting for review')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-m-document-text')
                ->color('warning'),

            Stat::make('Portfolio Balance', '$' . number_format(Cache::remember('filament.stats.portfolio_balance', $ttl, fn () => (float) Loan::where('status', 'active')->sum('amount')), 2))
                ->description('Active outstanding principal')
                ->descriptionIcon('heroicon-m-banknotes')
                ->icon('heroicon-m-wallet')
                ->color('info'),

            Stat::make('Active Borrowers', Cache::remember('filament.stats.active_borrowers', $ttl, fn () => Borrower::whereHas('loans', fn ($q) => $q->where('status', 'active'))->count()))
                ->description('Clients with active loans')
                ->descriptionIcon('heroicon-m-user-group')
                ->icon('heroicon-m-users')
                ->color('success'),

            Stat::make('MTD Disbursements', '$' . number_format(Cache::remember('filament.stats.mtd_disbursements', $ttl, fn () => (float) Loan::where('status', 'active')->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year)->sum('amount')), 2))
                ->description('Disbursed this month')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->icon('heroicon-m-arrow-trending-up')
                ->chart($disbursementTrend)
                ->color('success'),

            Stat::make('MTD Collections', '$' . number_format(Cache::remember('filament.stats.mtd_collections', $ttl, fn () => (float) RepaymentTransaction::whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->sum('amount_paid')), 2))
                ->description('Repayments this month')
                ->descriptionIcon('heroicon-m-check-badge')
                ->icon('heroicon-m-arrow-down-left')
                ->chart($collectionTrend)
                ->color('info'),

            Stat::make('Total Borrowing', '$' . number_format(Cache::remember('filament.stats.total_borrowing', $ttl, fn () => (float) Borrowing::where('status', 'active')->sum('amount')), 2))
                ->description('Active external funding')
                ->descriptionIcon('heroicon-m-building-library')
                ->icon('heroicon-m-building-library')
                ->color('primary'),
        ];
    }

    private function buildMonthlySeries(Builder $query, string $dateColumn, string $sumColumn, int $months = 6): array
    {
        $from = now()->copy()->subMonths($months - 1)->startOfMonth();

        $monthly = (clone $query)
            ->where($dateColumn, '>=', $from)
            ->selectRaw("DATE_FORMAT({$dateColumn}, '%Y-%m') as month_key, COALESCE(SUM({$sumColumn}), 0) as total")
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        return collect(range($months - 1, 0))
            ->map(fn (int $offset) => round((float) ($monthly[now()->subMonths($offset)->format('Y-m')] ?? 0), 2))
            ->values()
            ->all();
    }
}
