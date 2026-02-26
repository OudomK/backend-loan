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
            Stat::make('Pending Approvals', Loan::where('status', 'pending')->count())
                ->description('New loan applications')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Portfolio Balance', '$' . number_format(Loan::where('status', 'active')->sum('amount'), 2))
                ->description('Active outstanding principal')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Active Borrowers', Borrower::whereHas('loans', fn($q) => $q->where('status', 'active'))->count())
                ->description('Clients with active loans')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('MTD Disbursements', '$' . number_format(Loan::where('status', 'active')->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year)->sum('amount'), 2))
                ->description('Disbursed this month')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->color('success'),

            Stat::make('MTD Collections', '$' . number_format(RepaymentTransaction::whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->sum('amount_paid'), 2))
                ->description('Repayments this month')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),

            Stat::make('Total Savings', '$' . number_format(SavingAccount::sum('balance'), 2))
                ->description('Total customer deposits')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary'),
        ];
    }
}
