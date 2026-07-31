<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\Borrowing;
use App\Models\CapitalShare;
use App\Models\Investor;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardStatsService
{
    public function calculateAndCacheAll(): void
    {
        $ttl = 60 * 60; // 1 hour, but cron will refresh every 5 mins
        $referenceDate = Carbon::today();

        $exchangeRate = (float) (Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
            ?? Setting::where('key', 'exchange_rate')->value('value')
            ?? 4000);
        $exchangeRate = max(1, $exchangeRate);

        Cache::put('setting.exchange_rate_khr_to_usd', $exchangeRate, $ttl);

        $this->cacheCoreKpis($ttl);
        $this->cacheActiveLoansData($referenceDate, $exchangeRate, $ttl);
        $this->cacheMtdStats($ttl);
        $this->cacheBorrowingAndCapital($ttl);
        $this->cacheTrends($exchangeRate, $ttl);
        $this->cacheParAgingBuckets($ttl);
        $this->cacheMonthlyPerformance($exchangeRate, $ttl);
    }

    private function cacheCoreKpis(int $ttl): void
    {
        $pendingStatuses = ['pending', 'pending_check', 'pending_verify', 'pending_approval'];

        Cache::put('filament.stats.pending_loans_data', [
            'count' => Loan::whereIn('status', $pendingStatuses)->count(),
            'usd' => Loan::whereIn('status', $pendingStatuses)->where('currency', 'LIKE', 'USD%')->sum('amount'),
            'khr' => Loan::whereIn('status', $pendingStatuses)->where('currency', 'LIKE', 'KHR%')->sum('amount'),
        ], $ttl);

        Cache::put('filament.stats.active_borrowers', Borrower::whereHas('loans', fn ($q) => $q->where('status', 'active'))->count(), $ttl);

        Cache::put('filament.stats.written_off_data', [
            'count' => Loan::whereNotNull('written_off_at')->count(),
            'usd' => Loan::whereNotNull('written_off_at')->where('currency', 'LIKE', 'USD%')->sum('amount'),
            'khr' => Loan::whereNotNull('written_off_at')->where('currency', 'LIKE', 'KHR%')->sum('amount'),
        ], $ttl);

        Cache::put('filament.stats.total_investors', Investor::count(), $ttl);
    }

    private function cacheActiveLoansData(Carbon $referenceDate, float $exchangeRate, int $ttl): void
    {
        $activeLoans = Loan::with([
            'payments' => function ($query) {
                $query->orderBy('payment_date', 'asc');
            },
            'transactions' => function ($query) use ($referenceDate) {
                $query->where('transaction_date', '<=', $referenceDate->toDateString());
            },
        ])->where('status', 'active')->get();

        $outstandingUSD = 0.0;
        $outstandingKHR = 0.0;
        $parAmountUSD = 0.0;
        $parAmountKHR = 0.0;
        $par30AmountUSD = 0.0;
        $par30AmountKHR = 0.0;
        $par60AmountUSD = 0.0;
        $par60AmountKHR = 0.0;
        $par90AmountUSD = 0.0;
        $par90AmountKHR = 0.0;
        $overdueLoans = 0;

        foreach ($activeLoans as $loan) {
            /** @var Loan $loan */
            $snapshot = $this->portfolioSnapshot($loan, $referenceDate);
            $currentOS = $snapshot['outstanding'];
            if ($currentOS <= 0.01) {
                continue;
            }

            if (str_starts_with((string) ($loan->currency ?? 'USD'), 'KHR')) {
                $outstandingKHR += $currentOS;
                if ($snapshot['aging'] >= 1) {
                    $parAmountKHR += $currentOS;
                    $overdueLoans++;
                }
                if ($snapshot['aging'] >= 30) {
                    $par30AmountKHR += $currentOS;
                }
                if ($snapshot['aging'] >= 60) {
                    $par60AmountKHR += $currentOS;
                }
                if ($snapshot['aging'] >= 90) {
                    $par90AmountKHR += $currentOS;
                }
            } else {
                $outstandingUSD += $currentOS;
                if ($snapshot['aging'] >= 1) {
                    $parAmountUSD += $currentOS;
                    $overdueLoans++;
                }
                if ($snapshot['aging'] >= 30) {
                    $par30AmountUSD += $currentOS;
                }
                if ($snapshot['aging'] >= 60) {
                    $par60AmountUSD += $currentOS;
                }
                if ($snapshot['aging'] >= 90) {
                    $par90AmountUSD += $currentOS;
                }
            }
        }

        $portfolioBalance = $outstandingUSD + ($outstandingKHR / $exchangeRate);
        $parAmountUnified = $parAmountUSD + ($parAmountKHR / $exchangeRate);
        $par30Unified = $par30AmountUSD + ($par30AmountKHR / $exchangeRate);
        $par60Unified = $par60AmountUSD + ($par60AmountKHR / $exchangeRate);
        $par90Unified = $par90AmountUSD + ($par90AmountKHR / $exchangeRate);

        Cache::put('filament.stats.active_loans_data_multi', [
            'outstandingUSD' => $outstandingUSD,
            'outstandingKHR' => $outstandingKHR,
            'parAmountUSD' => $parAmountUSD,
            'parAmountKHR' => $parAmountKHR,
            'parPercentage' => $portfolioBalance > 0 ? round(($parAmountUnified / $portfolioBalance) * 100, 2) : 0,
            'par30AmountUSD' => $par30AmountUSD,
            'par30AmountKHR' => $par30AmountKHR,
            'par30Percentage' => $portfolioBalance > 0 ? round(($par30Unified / $portfolioBalance) * 100, 2) : 0,
            'par60AmountUSD' => $par60AmountUSD,
            'par60AmountKHR' => $par60AmountKHR,
            'par60Percentage' => $portfolioBalance > 0 ? round(($par60Unified / $portfolioBalance) * 100, 2) : 0,
            'par90AmountUSD' => $par90AmountUSD,
            'par90AmountKHR' => $par90AmountKHR,
            'par90Percentage' => $portfolioBalance > 0 ? round(($par90Unified / $portfolioBalance) * 100, 2) : 0,
            'overdueLoans' => $overdueLoans,
        ], $ttl);
    }

    private function cacheMtdStats(int $ttl): void
    {
        Cache::put('filament.stats.mtd_disbursements_split', [
            'usd' => Loan::where('status', 'active')->where('currency', 'LIKE', 'USD%')->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year)->sum('amount'),
            'khr' => Loan::where('status', 'active')->where('currency', 'LIKE', 'KHR%')->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year)->sum('amount'),
        ], $ttl);

        Cache::put('filament.stats.mtd_collections_split', [
            'usd' => RepaymentTransaction::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'USD%'))->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->sum('amount_paid'),
            'khr' => RepaymentTransaction::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'KHR%'))->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->sum('amount_paid'),
        ], $ttl);
        
        Cache::put('filament.stats.mtd_expected_usd_split', Payment::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'USD%'))->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->sum('total_due'), $ttl);
        Cache::put('filament.stats.mtd_expected_khr_split', Payment::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'KHR%'))->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->sum('total_due'), $ttl);
    }

    private function cacheBorrowingAndCapital(int $ttl): void
    {
        Cache::put('filament.stats.total_borrowing_split', [
            'usd' => Borrowing::where('status', 'active')->where('currency', 'LIKE', 'USD%')->sum('amount'),
            'khr' => Borrowing::where('status', 'active')->where('currency', 'LIKE', 'KHR%')->sum('amount'),
        ], $ttl);

        Cache::put('filament.stats.total_capital_shares_split', [
            'usd' => CapitalShare::where('status', 'active')->where('currency', 'LIKE', 'USD%')->sum('total_capital'),
            'khr' => CapitalShare::where('status', 'active')->where('currency', 'LIKE', 'KHR%')->sum('total_capital'),
        ], $ttl);
    }

    private function cacheTrends(float $exchangeRate, int $ttl): void
    {
        Cache::put('filament.stats.trend.disbursements_multi', $this->buildMonthlySeries(Loan::query()->where('status', 'active'), 'start_date', 'amount', false, $exchangeRate), $ttl);
        Cache::put('filament.stats.trend.collections_multi', $this->buildMonthlySeries(RepaymentTransaction::query(), 'transaction_date', 'amount_paid', true, $exchangeRate), $ttl);
        Cache::put('filament.stats.trend.pending_count', $this->buildMonthlyCountSeries(Loan::query()->where('status', 'pending'), 'created_at'), $ttl);
        Cache::put('filament.stats.trend.portfolio_balance', $this->buildMonthlySeries(Loan::query()->where('status', 'active'), 'start_date', 'amount', false, $exchangeRate), $ttl);
        Cache::put('filament.stats.trend.borrowers_count', $this->buildMonthlyCountSeries(Borrower::query(), 'created_at'), $ttl);
        Cache::put('filament.stats.trend.borrowing_sum', $this->buildMonthlySeries(Borrowing::query()->where('status', 'active'), 'created_at', 'amount', false, $exchangeRate), $ttl);
        Cache::put('filament.stats.trend.overdue_count', $this->buildMonthlyCountSeries(Loan::query()->where('status', 'active')->where('aging', '>', 0), 'updated_at'), $ttl);
        Cache::put('filament.stats.trend.written_off_sum', $this->buildMonthlySeries(Loan::query()->whereNotNull('written_off_at'), 'written_off_at', 'amount', false, $exchangeRate), $ttl);
        Cache::put('filament.stats.trend.investors_count', $this->buildMonthlyCountSeries(Investor::query(), 'created_at'), $ttl);
        Cache::put('filament.stats.trend.capital_sum', $this->buildMonthlySeries(CapitalShare::query()->where('status', 'active'), 'created_at', 'total_capital', false, $exchangeRate), $ttl);
    }

    private function cacheParAgingBuckets(int $ttl): void
    {
        $today = now()->toDateString();
        $agingLoans = Loan::where('status', 'active')
            ->select('id')
            ->addSelect([
                'real_aging' => Payment::selectRaw('DATEDIFF(?, MIN(payment_date))', [$today])
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

        foreach ($agingLoans as $agingLoan) {
            $aging = $agingLoan->real_aging ?? 0;
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
        Cache::put('filament.stats.par_aging_buckets', $buckets, $ttl);
    }

    private function cacheMonthlyPerformance(float $exchangeRate, int $ttl): void
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->startOfMonth()->subMonths($i)->format('Y-m');
        }

        $disbursementsRaw = Loan::where('status', 'active')
            ->where('start_date', '>=', now()->startOfMonth()->subMonths(11))
            ->selectRaw('DATE_FORMAT(start_date, "%Y-%m") as month, currency, SUM(amount) as total_amount')
            ->groupBy('month', 'currency')
            ->get();

        $disbursements = array_fill_keys($months, 0);
        foreach ($disbursementsRaw as $d) {
            $amount = $d->total_amount;
            if (str_starts_with($d->currency, 'KHR')) {
                $amount = $amount / $exchangeRate;
            }
            $disbursements[$d->month] = ($disbursements[$d->month] ?? 0) + $amount;
        }

        $collectionsRaw = RepaymentTransaction::where('transaction_date', '>=', now()->startOfMonth()->subMonths(11))
            ->with('loan')
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->transaction_date)->format('Y-m');
            });

        $collections = array_fill_keys($months, 0);
        foreach ($collectionsRaw as $month => $transactions) {
            $totalForMonth = 0;
            foreach ($transactions as $t) {
                $amount = $t->amount_paid;
                if ($t->loan && str_starts_with($t->loan->currency, 'KHR')) {
                    $amount = $amount / $exchangeRate;
                }
                $totalForMonth += $amount;
            }
            $collections[$month] = $totalForMonth;
        }

        Cache::put('filament.stats.monthly_performance', [
            'disbursements' => $disbursements,
            'collections' => $collections,
        ], $ttl);
    }

    private function buildMonthlySeries(Builder $query, string $dateColumn, string $sumColumn, bool $isLoanRelation = false, float $exchangeRate = 4000, int $months = 6): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        $recordsQuery = (clone $query)->where($dateColumn, '>=', $from);
        if ($isLoanRelation) {
            $recordsQuery->with('loan');
        }
        $records = $recordsQuery->get();

        $monthlyData = [];
        foreach (range($months - 1, 0) as $offset) {
            $monthlyData[now()->startOfMonth()->subMonths($offset)->format('Y-m')] = 0;
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
        $from = now()->startOfMonth()->subMonths($months - 1);
        $records = (clone $query)->where($dateColumn, '>=', $from)->get();

        $monthlyData = [];
        foreach (range($months - 1, 0) as $offset) {
            $monthlyData[now()->startOfMonth()->subMonths($offset)->format('Y-m')] = 0;
        }

        foreach ($records as $record) {
            $month = Carbon::parse($record->{$dateColumn})->format('Y-m');
            if (isset($monthlyData[$month])) {
                $monthlyData[$month]++;
            }
        }

        return collect($monthlyData)->values()->all();
    }

    private function portfolioSnapshot(Loan $loan, Carbon $referenceDate): array
    {
        $transactionsAtDate = $loan->transactions ?? collect();

        $principalPaid = $transactionsAtDate->sum(function ($transaction) {
            return (float) ($transaction->principal_paid ?? 0)
                + (float) ($transaction->prepayment_paid ?? 0)
                + (float) ($transaction->paid_off_amount ?? 0)
                - (float) ($transaction->withdrawn_prepayment ?? 0);
        });

        $outstanding = max(0, (float) $loan->amount - $principalPaid);
        if ($outstanding <= 0.01) {
            return ['outstanding' => 0.0, 'aging' => 0];
        }

        $scheduledPaid = $transactionsAtDate->sum(function ($transaction) {
            return (float) ($transaction->fee_paid ?? 0)
                + (float) ($transaction->interest_paid ?? 0)
                + (float) ($transaction->principal_paid ?? 0)
                + (float) ($transaction->prepayment_paid ?? 0)
                + (float) ($transaction->paid_off_amount ?? 0)
                - (float) ($transaction->withdrawn_prepayment ?? 0);
        });

        $cumulativeDue = 0.0;
        $cumulativePrincipalDue = 0.0;
        $earliestArrearDate = null;
        $earliestPrincipalArrearDate = null;

        foreach ($loan->payments as $payment) {
            if (($payment->payment_date ?? '') >= $referenceDate->toDateString()) {
                continue;
            }

            $cumulativeDue += (float) ($payment->principal_amount ?? 0)
                + (float) ($payment->interest_amount ?? 0)
                + (float) ($payment->fee_amount ?? 0);
            $cumulativePrincipalDue += (float) ($payment->principal_amount ?? 0);

            if (($cumulativeDue - $scheduledPaid) > 0.01 && $earliestArrearDate === null) {
                $earliestArrearDate = $payment->payment_date;
            }

            if (($cumulativePrincipalDue - $principalPaid) > 0.01 && $earliestPrincipalArrearDate === null) {
                $earliestPrincipalArrearDate = $payment->payment_date;
            }
        }

        $effectiveArrearDate = $earliestArrearDate ?? $earliestPrincipalArrearDate;
        $aging = 0;

        if ($effectiveArrearDate) {
            $aging = abs($referenceDate->copy()->startOfDay()->diffInDays(
                Carbon::parse($effectiveArrearDate)->startOfDay()
            ));
        }

        if ($aging <= 0 && ($cumulativeDue - $scheduledPaid) > 0.01) {
            $aging = 1;
        }

        return [
            'outstanding' => $outstanding,
            'aging' => $aging,
        ];
    }
}
