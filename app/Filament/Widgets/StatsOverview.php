<?php

namespace App\Filament\Widgets;

use App\Models\Borrower;
use App\Models\Borrowing;
use App\Models\CapitalShare;
use App\Models\Investor;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use App\Models\Setting;
use App\Support\CurrencyHelper;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;
    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        // Default TTL for inline caching if the scheduler hasn't run yet
        $ttl = 60 * 60; 

        // Core KPIs (Row 1)
        $pendingLoansData = Cache::remember('filament.stats.pending_loans_data', $ttl, function() {
            app(\App\Services\DashboardStatsService::class)->calculateAndCacheAll();
            return Cache::get('filament.stats.pending_loans_data', ['count' => 0, 'usd' => 0, 'khr' => 0]);
        });

        $activeBorrowers = Cache::get('filament.stats.active_borrowers', 0);

        $writtenOffData = Cache::get('filament.stats.written_off_data', ['count' => 0, 'usd' => 0, 'khr' => 0]);

        $totalInvestors = Cache::get('filament.stats.total_investors', 0);

        // Dynamic Portfolio and PAR calculations
        $activeLoansData = Cache::get('filament.stats.active_loans_data_multi', [
            'outstandingUSD' => 0, 'outstandingKHR' => 0,
            'parAmountUSD' => 0, 'parAmountKHR' => 0, 'parPercentage' => 0,
            'par30AmountUSD' => 0, 'par30AmountKHR' => 0, 'par30Percentage' => 0,
            'par60AmountUSD' => 0, 'par60AmountKHR' => 0, 'par60Percentage' => 0,
            'par90AmountUSD' => 0, 'par90AmountKHR' => 0, 'par90Percentage' => 0,
            'overdueLoans' => 0,
        ]);

        $outstandingUSD = $activeLoansData['outstandingUSD'];
        $outstandingKHR = $activeLoansData['outstandingKHR'];
        $parAmountUSD = $activeLoansData['parAmountUSD'];
        $parAmountKHR = $activeLoansData['parAmountKHR'];
        $parPercentage = $activeLoansData['parPercentage'];
        $par30AmountUSD = $activeLoansData['par30AmountUSD'];
        $par30AmountKHR = $activeLoansData['par30AmountKHR'];
        $par30Percentage = $activeLoansData['par30Percentage'];
        $par60AmountUSD = $activeLoansData['par60AmountUSD'];
        $par60AmountKHR = $activeLoansData['par60AmountKHR'];
        $par60Percentage = $activeLoansData['par60Percentage'];
        $par90AmountUSD = $activeLoansData['par90AmountUSD'];
        $par90AmountKHR = $activeLoansData['par90AmountKHR'];
        $par90Percentage = $activeLoansData['par90Percentage'];
        $overdueLoans = $activeLoansData['overdueLoans'];

        // Disbursements & Collections MTD
        $disbursements = Cache::get('filament.stats.mtd_disbursements_split', ['usd' => 0, 'khr' => 0]);
        $collections = Cache::get('filament.stats.mtd_collections_split', ['usd' => 0, 'khr' => 0]);
        $mtdExpectedUSD = Cache::get('filament.stats.mtd_expected_usd_split', 0);
        $mtdExpectedKHR = Cache::get('filament.stats.mtd_expected_khr_split', 0);
        
        $exchangeRate = (float) Cache::get('setting.exchange_rate_khr_to_usd', 4000);
        $exchangeRate = max(1, $exchangeRate);

        $totalExpectedCombined = $mtdExpectedUSD + ($mtdExpectedKHR / $exchangeRate);
        $totalCollectedCombined = $collections['usd'] + ($collections['khr'] / $exchangeRate);
        $collectionRate = $totalExpectedCombined > 0 ? round(($totalCollectedCombined / $totalExpectedCombined) * 100, 1) : 0;

        // Borrowing
        $borrowing = Cache::get('filament.stats.total_borrowing_split', ['usd' => 0, 'khr' => 0]);

        // Capital Shares
        $capital = Cache::get('filament.stats.total_capital_shares_split', ['usd' => 0, 'khr' => 0]);

        // ── Trends ───────────────────────────────────────────────────────
        $disbursementTrend = Cache::get('filament.stats.trend.disbursements_multi', []);
        $collectionTrend = Cache::get('filament.stats.trend.collections_multi', []);
        $pendingTrend = Cache::get('filament.stats.trend.pending_count', []);
        $portfolioTrend = Cache::get('filament.stats.trend.portfolio_balance', []);
        $borrowersTrend = Cache::get('filament.stats.trend.borrowers_count', []);
        $borrowingTrend = Cache::get('filament.stats.trend.borrowing_sum', []);
        $overdueTrend = Cache::get('filament.stats.trend.overdue_count', []);
        $writtenOffTrend = Cache::get('filament.stats.trend.written_off_sum', []);
        $investorsTrend = Cache::get('filament.stats.trend.investors_count', []);
        $capitalTrend = Cache::get('filament.stats.trend.capital_sum', []);

        return [
            // ── Row 1: Core KPIs ─────────────────────────────────────────
            Stat::make('Pending Approvals', new \Illuminate\Support\HtmlString($pendingLoansData['count'] . ' <span class="text-sm font-normal text-gray-500">| ' . CurrencyHelper::displayDualPlain($pendingLoansData['usd'], $pendingLoansData['khr']) . '</span>'))
                ->description('Applications waiting for review')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-m-document-text')
                ->chart($pendingTrend)
                ->color('warning')
                ->url(\App\Filament\Resources\Loans\LoanResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'pending_check']],
                ])),

            Stat::make('Portfolio Balance', new \Illuminate\Support\HtmlString(CurrencyHelper::displayDual($outstandingUSD, $outstandingKHR)))
                ->description('Active outstanding principal')
                ->descriptionIcon('heroicon-m-banknotes')
                ->icon('heroicon-m-wallet')
                ->chart($portfolioTrend)
                ->color('info'),

            Stat::make('Active Borrowers', $activeBorrowers)
                ->description('Clients with active loans')
                ->descriptionIcon('heroicon-m-user-group')
                ->icon('heroicon-m-users')
                ->chart($borrowersTrend)
                ->color('success'),

            Stat::make('MTD Disbursements', new \Illuminate\Support\HtmlString(CurrencyHelper::displayDual($disbursements['usd'], $disbursements['khr'])))
                ->description('Disbursed this month')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->icon('heroicon-m-arrow-trending-up')
                ->chart($disbursementTrend)
                ->color('success'),

            Stat::make('MTD Collections', new \Illuminate\Support\HtmlString(CurrencyHelper::displayDual($collections['usd'], $collections['khr'])))
                ->description('Repayments this month')
                ->descriptionIcon('heroicon-m-check-badge')
                ->icon('heroicon-m-arrow-down-left')
                ->chart($collectionTrend)
                ->color('info'),

            Stat::make('Total Borrowing', new \Illuminate\Support\HtmlString(CurrencyHelper::displayDual($borrowing['usd'], $borrowing['khr'])))
                ->description('Active external funding')
                ->descriptionIcon('heroicon-m-building-library')
                ->icon('heroicon-m-building-library')
                ->chart($borrowingTrend)
                ->color('gray'),

            // ── Row 2: Risk & Fund Metrics ───────────────────────────────
            Stat::make('PAR 30%', $par30Percentage . '%')
                ->description(new \Illuminate\Support\HtmlString(
                    CurrencyHelper::display($par30AmountUSD, CurrencyHelper::USD, 0)
                    . ' &bull; '
                    . CurrencyHelper::display($par30AmountKHR, CurrencyHelper::KHR, 0)
                    . ' at risk'
                ))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->icon('heroicon-m-shield-exclamation')
                ->chart([3, 5, 4, 6, 5, 7, $par30Percentage])
                ->color($par30Percentage > 5 ? 'danger' : ($par30Percentage > 2 ? 'warning' : 'success')),

            Stat::make('Overdue Loans', new \Illuminate\Support\HtmlString($overdueLoans . ' <span class="text-sm font-normal text-gray-500">| ' . CurrencyHelper::displayDualPlain($parAmountUSD, $parAmountKHR) . '</span>'))
                ->description('Loans past due date')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->icon('heroicon-m-bell-alert')
                ->chart($overdueTrend)
                ->color($overdueLoans > 0 ? 'danger' : 'success'),

            Stat::make('Collection Rate', $collectionRate . '%')
                ->description('MTD collected vs expected')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->icon('heroicon-m-chart-bar-square')
                ->chart([85, 90, 88, 92, 87, 91, $collectionRate])
                ->color($collectionRate >= 90 ? 'success' : ($collectionRate >= 70 ? 'warning' : 'danger')),

            Stat::make('Written-Off', new \Illuminate\Support\HtmlString($writtenOffData['count'] . ' <span class="text-sm font-normal text-gray-500">| ' . CurrencyHelper::displayDualPlain($writtenOffData['usd'], $writtenOffData['khr']) . '</span>'))
                ->description('Total loans written off')
                ->descriptionIcon('heroicon-m-x-circle')
                ->icon('heroicon-m-trash')
                ->chart($writtenOffTrend)
                ->color('danger'),

            Stat::make('Investors', $totalInvestors)
                ->description('Registered investors')
                ->descriptionIcon('heroicon-m-user-plus')
                ->icon('heroicon-m-briefcase')
                ->chart($investorsTrend)
                ->color('primary'),

            Stat::make('Capital Shares', new \Illuminate\Support\HtmlString(CurrencyHelper::displayDual($capital['usd'], $capital['khr'])))
                ->description('Active share capital')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->icon('heroicon-m-banknotes')
                ->chart($capitalTrend)
                ->color('info'),
        ];
    }
}
