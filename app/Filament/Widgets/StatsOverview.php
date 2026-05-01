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
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $ttl = 60; // 1 minute cache
        $referenceDate = Carbon::today();
        
        $exchangeRate = (float) (Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
            ?? Setting::where('key', 'exchange_rate')->value('value')
            ?? 4000);
        $exchangeRate = max(1, $exchangeRate);

        $disbursementTrend = Cache::remember('filament.stats.trend.disbursements_multi', $ttl, fn () => $this->buildMonthlySeries(
            Loan::query()->where('status', 'active'),
            'start_date',
            'amount',
            false
        ));
        
        $collectionTrend = Cache::remember('filament.stats.trend.collections_multi', $ttl, fn () => $this->buildMonthlySeries(
            RepaymentTransaction::query(),
            'transaction_date',
            'amount_paid',
            true
        ));

        // ── Core KPIs (Row 1) ────────────────────────────────────────────

        $pendingLoansData = Cache::remember('filament.stats.pending_loans_data', $ttl, function() {
            return [
                'count' => Loan::where('status', 'pending')->count(),
                'usd' => Loan::where('status', 'pending')->where('currency', 'LIKE', 'USD%')->sum('amount'),
                'khr' => Loan::where('status', 'pending')->where('currency', 'LIKE', 'KHR%')->sum('amount'),
            ];
        });

        $activeBorrowers = Cache::remember('filament.stats.active_borrowers', $ttl, fn () => Borrower::whereHas('loans', fn ($q) => $q->where('status', 'active'))->count());

        $writtenOffData = Cache::remember('filament.stats.written_off_data', $ttl, function() {
            return [
                'count' => Loan::whereNotNull('written_off_at')->count(),
                'usd' => Loan::whereNotNull('written_off_at')->where('currency', 'LIKE', 'USD%')->sum('amount'),
                'khr' => Loan::whereNotNull('written_off_at')->where('currency', 'LIKE', 'KHR%')->sum('amount'),
            ];
        });

        $totalInvestors = Cache::remember('filament.stats.total_investors', $ttl, fn () => Investor::count());

        // Dynamic Portfolio and PAR calculations
        $activeLoansData = Cache::remember('filament.stats.active_loans_data_multi', $ttl, function() use ($referenceDate, $exchangeRate) {
            
            // Match exactly with Frontend (DashboardController) for Portfolio Balance
            $disbursedUSD = Loan::where('currency', 'LIKE', 'USD%')->sum('amount');
            $disbursedKHR = Loan::where('currency', 'LIKE', 'KHR%')->sum('amount');
            $disbursedAmount = $disbursedUSD + ($disbursedKHR / $exchangeRate);

            $paidUSD = Payment::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'USD%'))
                ->sum(DB::raw('GREATEST(0, total_paid - interest_amount)'));
            $paidKHR = Payment::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'KHR%'))
                ->sum(DB::raw('GREATEST(0, total_paid - interest_amount)'));

            $outstandingUSD = $disbursedUSD - $paidUSD;
            $outstandingKHR = $disbursedKHR - $paidKHR;
            $portfolioBalance = $outstandingUSD + ($outstandingKHR / $exchangeRate);

            // Match PAR exactly with Frontend
            $activeLoans = Loan::with('payments')->where('status', 'active')->get();
            $parAmountUSD = 0;
            $parAmountKHR = 0;
            $overdueLoans = 0;

            foreach ($activeLoans as $loan) {
                // Determine if loan is overdue
                $isOverdue = $loan->payments->contains(function ($payment) use ($referenceDate) {
                    return $payment->payment_date < $referenceDate->toDateString() 
                        && $payment->total_paid < ($payment->principal_amount + $payment->interest_amount - 0.01);
                });

                if ($isOverdue) {
                    $loanPrincipalPaid = $loan->payments->sum(function ($payment) {
                        return max(0, $payment->total_paid - $payment->interest_amount);
                    });
                    
                    $loanOutstanding = $loan->amount - $loanPrincipalPaid;

                    if (str_starts_with($loan->currency ?? 'USD', 'KHR')) {
                        $parAmountKHR += $loanOutstanding;
                    } else {
                        $parAmountUSD += $loanOutstanding;
                    }

                    $overdueLoans++;
                }
            }
            
            $parAmountUnified = $parAmountUSD + ($parAmountKHR / $exchangeRate);

            return [
                'outstandingUSD' => $outstandingUSD,
                'outstandingKHR' => $outstandingKHR,
                'parAmountUSD' => $parAmountUSD,
                'parAmountKHR' => $parAmountKHR,
                'parPercentage' => $portfolioBalance > 0 ? round(($parAmountUnified / $portfolioBalance) * 100, 2) : 0,
                'overdueLoans' => $overdueLoans,
            ];
        });

        $outstandingUSD = $activeLoansData['outstandingUSD'];
        $outstandingKHR = $activeLoansData['outstandingKHR'];
        $parAmountUSD = $activeLoansData['parAmountUSD'];
        $parAmountKHR = $activeLoansData['parAmountKHR'];
        $parPercentage = $activeLoansData['parPercentage'];
        $overdueLoans = $activeLoansData['overdueLoans'];

        // Currencies mapping helpers
        // Disbursements
        $disbursements = Cache::remember('filament.stats.mtd_disbursements_split', $ttl, function() {
            $usd = Loan::where('status', 'active')->where('currency', 'LIKE', 'USD%')->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year)->sum('amount');
            $khr = Loan::where('status', 'active')->where('currency', 'LIKE', 'KHR%')->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year)->sum('amount');
            return ['usd' => $usd, 'khr' => $khr];
        });

        // Collections
        $collections = Cache::remember('filament.stats.mtd_collections_split', $ttl, function() {
            $usd = RepaymentTransaction::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'USD%'))
                ->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->sum('amount_paid');
            $khr = RepaymentTransaction::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'KHR%'))
                ->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->sum('amount_paid');
            return ['usd' => $usd, 'khr' => $khr];
        });
        
        // Expected Collections MTD
        $mtdExpectedUSD = Cache::remember('filament.stats.mtd_expected_usd_split', $ttl, function() {
            return Payment::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'USD%'))
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('total_due');
        });
        $mtdExpectedKHR = Cache::remember('filament.stats.mtd_expected_khr_split', $ttl, function() {
            return Payment::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'KHR%'))
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('total_due');
        });
        
        // Use the total combined expected amount in USD to calculate the rate
        $totalExpectedCombined = $mtdExpectedUSD + ($mtdExpectedKHR / $exchangeRate);
        $totalCollectedCombined = $collections['usd'] + ($collections['khr'] / $exchangeRate);
        $collectionRate = $totalExpectedCombined > 0 ? round(($totalCollectedCombined / $totalExpectedCombined) * 100, 1) : 0;

        // Borrowing
        $borrowing = Cache::remember('filament.stats.total_borrowing_split', $ttl, function() {
            $usd = Borrowing::where('status', 'active')->where('currency', 'LIKE', 'USD%')->sum('amount');
            $khr = Borrowing::where('status', 'active')->where('currency', 'LIKE', 'KHR%')->sum('amount');
            return ['usd' => $usd, 'khr' => $khr];
        });

        // Capital Shares
        $capital = Cache::remember('filament.stats.total_capital_shares_split', $ttl, function() {
            $usd = CapitalShare::where('status', 'active')->where('currency', 'LIKE', 'USD%')->sum('total_capital');
            $khr = CapitalShare::where('status', 'active')->where('currency', 'LIKE', 'KHR%')->sum('total_capital');
            return ['usd' => $usd, 'khr' => $khr];
        });

        // ── Trends ───────────────────────────────────────────────────────
        
        $pendingTrend = Cache::remember('filament.stats.trend.pending_count', $ttl, fn() => $this->buildMonthlyCountSeries(Loan::where('status', 'pending'), 'created_at'));
        
        $portfolioTrend = Cache::remember('filament.stats.trend.portfolio_balance', $ttl, fn() => $this->buildMonthlySeries(
            Loan::query()->where('status', 'active'),
            'start_date',
            'amount',
            false
        ));

        $borrowersTrend = Cache::remember('filament.stats.trend.borrowers_count', $ttl, fn() => $this->buildMonthlyCountSeries(Borrower::query(), 'created_at'));
        
        $borrowingTrend = Cache::remember('filament.stats.trend.borrowing_sum', $ttl, fn() => $this->buildMonthlySeries(
            Borrowing::query()->where('status', 'active'),
            'created_at',
            'amount',
            false
        ));

        $overdueTrend = Cache::remember('filament.stats.trend.overdue_count', $ttl, fn() => $this->buildMonthlyCountSeries(Loan::where('status', 'active')->where('aging', '>', 0), 'updated_at'));
        
        $writtenOffTrend = Cache::remember('filament.stats.trend.written_off_sum', $ttl, fn() => $this->buildMonthlySeries(
            Loan::query()->whereNotNull('written_off_at'),
            'written_off_at',
            'amount',
            false
        ));

        $investorsTrend = Cache::remember('filament.stats.trend.investors_count', $ttl, fn() => $this->buildMonthlyCountSeries(Investor::query(), 'created_at'));
        
        $capitalTrend = Cache::remember('filament.stats.trend.capital_sum', $ttl, fn() => $this->buildMonthlySeries(
            CapitalShare::query()->where('status', 'active'),
            'created_at',
            'total_capital',
            false
        ));

        return [
            // ── Row 1: Core KPIs ─────────────────────────────────────────
            Stat::make('Pending Approvals', new \Illuminate\Support\HtmlString($pendingLoansData['count'] . ' <span class="text-sm font-normal text-gray-500">| $' . number_format($pendingLoansData['usd'], 0) . '</span>'))
                ->description('Applications waiting for review')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-m-document-text')
                ->chart($pendingTrend)
                ->color('warning'),

            Stat::make('Portfolio Balance', new \Illuminate\Support\HtmlString('$' . number_format($outstandingUSD, 0) . ' <span class="text-sm font-normal text-gray-500">| ៛' . number_format($outstandingKHR, 0) . '</span>'))
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

            Stat::make('MTD Disbursements', new \Illuminate\Support\HtmlString('$' . number_format($disbursements['usd'], 0) . ' <span class="text-sm font-normal text-gray-500">| ៛' . number_format($disbursements['khr'], 0) . '</span>'))
                ->description('Disbursed this month')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->icon('heroicon-m-arrow-trending-up')
                ->chart($disbursementTrend)
                ->color('success'),

            Stat::make('MTD Collections', new \Illuminate\Support\HtmlString('$' . number_format($collections['usd'], 0) . ' <span class="text-sm font-normal text-gray-500">| ៛' . number_format($collections['khr'], 0) . '</span>'))
                ->description('Repayments this month')
                ->descriptionIcon('heroicon-m-check-badge')
                ->icon('heroicon-m-arrow-down-left')
                ->chart($collectionTrend)
                ->color('info'),

            Stat::make('Total Borrowing', new \Illuminate\Support\HtmlString('$' . number_format($borrowing['usd'], 0) . ' <span class="text-sm font-normal text-gray-500">| ៛' . number_format($borrowing['khr'], 0) . '</span>'))
                ->description('Active external funding')
                ->descriptionIcon('heroicon-m-building-library')
                ->icon('heroicon-m-building-library')
                ->chart($borrowingTrend)
                ->color('gray'),

            // ── Row 2: Risk & Fund Metrics ───────────────────────────────
            Stat::make('Portfolio At Risk (PAR%)', $parPercentage . '%')
                ->description(new \Illuminate\Support\HtmlString('$' . number_format($parAmountUSD, 0) . ' &bull; ៛' . number_format($parAmountKHR, 0) . ' at risk'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->icon('heroicon-m-shield-exclamation')
                ->chart([3, 5, 4, 6, 5, 7, $parPercentage])
                ->color($parPercentage > 5 ? 'danger' : ($parPercentage > 2 ? 'warning' : 'success')),

            Stat::make('Overdue Loans', new \Illuminate\Support\HtmlString($overdueLoans . ' <span class="text-sm font-normal text-gray-500">| $' . number_format($parAmountUSD, 0) . '</span>'))
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

            Stat::make('Written-Off', new \Illuminate\Support\HtmlString($writtenOffData['count'] . ' <span class="text-sm font-normal text-gray-500">| $' . number_format($writtenOffData['usd'], 0) . '</span>'))
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

            Stat::make('Capital Shares', new \Illuminate\Support\HtmlString('$' . number_format($capital['usd'], 0) . ' <span class="text-sm font-normal text-gray-500">| ៛' . number_format($capital['khr'], 0) . '</span>'))
                ->description('Active share capital')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->icon('heroicon-m-banknotes')
                ->chart($capitalTrend)
                ->color('info'),
        ];
    }

    private function buildMonthlySeries(Builder $query, string $dateColumn, string $sumColumn, bool $isLoanRelation = false, int $months = 6): array
    {
        $exchangeRate = (float) (Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
            ?? Setting::where('key', 'exchange_rate')->value('value')
            ?? 4000);
        $exchangeRate = max(1, $exchangeRate);

        $from = now()->copy()->subMonths($months - 1)->startOfMonth();

        $recordsQuery = (clone $query)->where($dateColumn, '>=', $from);
        if ($isLoanRelation) {
            $recordsQuery->with('loan');
        }
        
        $records = $recordsQuery->get();

        $monthlyData = [];
        foreach (range($months - 1, 0) as $offset) {
            $monthlyData[now()->subMonths($offset)->format('Y-m')] = 0;
        }

        foreach ($records as $record) {
            $month = Carbon::parse($record->{$dateColumn})->format('Y-m');
            if (isset($monthlyData[$month])) {
                $currency = $isLoanRelation ? ($record->loan ? $record->loan->currency : 'USD') : $record->currency;
                $amount = $record->{$sumColumn};
                if (str_starts_with($currency ?? 'USD', 'KHR')) {
                    $amount = $amount / $exchangeRate;
                }
                $monthlyData[$month] += $amount;
            }
        }

        return collect($monthlyData)->values()->map(fn($v) => round($v, 2))->all();
    }

    private function buildMonthlyCountSeries(Builder $query, string $dateColumn, int $months = 6): array
    {
        $from = now()->copy()->subMonths($months - 1)->startOfMonth();
        $records = (clone $query)->where($dateColumn, '>=', $from)->get();

        $monthlyData = [];
        foreach (range($months - 1, 0) as $offset) {
            $monthlyData[now()->subMonths($offset)->format('Y-m')] = 0;
        }

        foreach ($records as $record) {
            $month = Carbon::parse($record->{$dateColumn})->format('Y-m');
            if (isset($monthlyData[$month])) {
                $monthlyData[$month]++;
            }
        }

        return collect($monthlyData)->values()->all();
    }
}

