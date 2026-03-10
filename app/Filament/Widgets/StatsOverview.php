<?php

namespace App\Filament\Widgets;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\RepaymentTransaction;
use App\Models\SavingAccount;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $ttl = 60; // 1 minute cache

        return [
            Stat::make('Pending Approvals', Cache::remember('filament.stats.pending_loans', $ttl, fn () => Loan::where('status', 'pending')->count()))
                ->description('New loan applications')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Portfolio Balance', '$' . number_format(Cache::remember('filament.stats.portfolio_balance', $ttl, fn () => (float) Loan::where('status', 'active')->sum('amount')), 2))
                ->description('Active outstanding principal')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Active Borrowers', Cache::remember('filament.stats.active_borrowers', $ttl, fn () => Borrower::whereHas('loans', fn ($q) => $q->where('status', 'active'))->count()))
                ->description('Clients with active loans')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('MTD Disbursements', '$' . number_format(Cache::remember('filament.stats.mtd_disbursements', $ttl, fn () => (float) Loan::where('status', 'active')->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year)->sum('amount')), 2))
                ->description('Disbursed this month')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->color('success'),

            Stat::make('MTD Collections', '$' . number_format(Cache::remember('filament.stats.mtd_collections', $ttl, fn () => (float) RepaymentTransaction::whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->sum('amount_paid')), 2))
                ->description('Repayments this month')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),

            Stat::make('Total Savings', '$' . number_format(Cache::remember('filament.stats.total_savings', $ttl, fn () => (float) SavingAccount::sum('balance')), 2))
                ->description('Total customer deposits')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary'),
        ];
    }
}
