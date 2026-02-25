<?php

namespace App\Filament\Widgets;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\RepaymentTransaction;
use App\Models\SavingAccount;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Borrowers', Borrower::whereHas('loans', fn($q) => $q->where('status', 'active'))->count())
                ->description('Clients with active loans')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
            Stat::make('Portfolio Balance', '$' . number_format(Loan::where('status', 'Active')->sum('amount'), 2))
                ->description('Total outstanding principal')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
            Stat::make('Total Repayments', '$' . number_format(RepaymentTransaction::sum('amount_paid'), 2))
                ->description('Total collected revenue')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
            Stat::make('Active Savings', SavingAccount::where('status', 'Active')->count())
                ->description('Current saving accounts')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('warning'),
            Stat::make('Total Investors', \App\Models\Investor::count())
                ->description('Total funding partners')
                ->descriptionIcon('heroicon-m-hand-raised')
                ->color('danger'),
        ];
    }
}
