<?php

namespace App\Filament\Pages;

use App\Models\Loan;
use App\Models\Setting;
use Carbon\Carbon;
use Filament\Pages\Page;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ParAnalysis extends Page
{
    use HasPageShield {
        canAccess as shieldCanAccess;
    }

    public static function canAccess(): bool
    {
        if (!\App\Services\FeatureToggle::isAccessible('par_analysis', \Filament\Facades\Filament::auth()->user())) {
            return false;
        }
        return static::shieldCanAccess();
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static string|\UnitEnum|null $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'PAR Analysis';
    protected static ?string $title = 'PAR Analysis';
    protected static ?int $navigationSort = 15;
    protected ?string $heading = '';

    protected string $view = 'filament.pages.par-analysis';

    public function getParMetrics(): array
    {
        $referenceDate = Carbon::today();
        $exchangeRate = (float) (Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
            ?? Setting::where('key', 'exchange_rate')->value('value')
            ?? 4000);
        $exchangeRate = max(1, $exchangeRate);

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
        $levels = [
            'par1' => ['usd' => 0.0, 'khr' => 0.0, 'count' => 0, 'days' => 1],
            'par30' => ['usd' => 0.0, 'khr' => 0.0, 'count' => 0, 'days' => 30],
            'par60' => ['usd' => 0.0, 'khr' => 0.0, 'count' => 0, 'days' => 60],
            'par90' => ['usd' => 0.0, 'khr' => 0.0, 'count' => 0, 'days' => 90],
        ];
        $buckets = [
            'standard' => ['label' => '0-30 Days (Standard)', 'usd' => 0.0, 'khr' => 0.0, 'count' => 0],
            'special_mention' => ['label' => '31-89 Days (Special Mention)', 'usd' => 0.0, 'khr' => 0.0, 'count' => 0],
            'substandard' => ['label' => '90-179 Days (Substandard)', 'usd' => 0.0, 'khr' => 0.0, 'count' => 0],
            'doubtful' => ['label' => '180-359 Days (Doubtful)', 'usd' => 0.0, 'khr' => 0.0, 'count' => 0],
            'loss' => ['label' => '360+ Days (Loss)', 'usd' => 0.0, 'khr' => 0.0, 'count' => 0],
        ];

        /** @var Loan $loan */
        foreach ($activeLoans as $loan) {
            $snapshot = $this->portfolioSnapshot($loan, $referenceDate);
            $currentOS = $snapshot['outstanding'];
            if ($currentOS <= 0.01) {
                continue;
            }

            $currencyKey = str_starts_with((string) ($loan->currency ?? 'USD'), 'KHR') ? 'khr' : 'usd';
            if ($currencyKey === 'khr') {
                $outstandingKHR += $currentOS;
            } else {
                $outstandingUSD += $currentOS;
            }

            foreach ($levels as $key => $level) {
                if ($snapshot['aging'] >= $level['days']) {
                    $levels[$key][$currencyKey] += $currentOS;
                    $levels[$key]['count']++;
                }
            }

            $bucketKey = match (true) {
                $snapshot['aging'] <= 30 => 'standard',
                $snapshot['aging'] <= 89 => 'special_mention',
                $snapshot['aging'] <= 179 => 'substandard',
                $snapshot['aging'] <= 359 => 'doubtful',
                default => 'loss',
            };
            $buckets[$bucketKey][$currencyKey] += $currentOS;
            $buckets[$bucketKey]['count']++;
        }

        $portfolioBalance = $outstandingUSD + ($outstandingKHR / $exchangeRate);

        foreach ($levels as $key => $level) {
            $converted = $level['usd'] + ($level['khr'] / $exchangeRate);
            $levels[$key]['usd_equivalent'] = $converted;
            $levels[$key]['percent'] = $portfolioBalance > 0
                ? round(($converted / $portfolioBalance) * 100, 2)
                : 0.0;
        }

        foreach ($buckets as $key => $bucket) {
            $converted = $bucket['usd'] + ($bucket['khr'] / $exchangeRate);
            $buckets[$key]['usd_equivalent'] = $converted;
            $buckets[$key]['share_percent'] = $portfolioBalance > 0
                ? round(($converted / $portfolioBalance) * 100, 2)
                : 0.0;
        }

        return [
            'reference_date' => $referenceDate->format('d/m/Y'),
            'portfolio_usd' => $outstandingUSD,
            'portfolio_khr' => $outstandingKHR,
            'portfolio_usd_equivalent' => $portfolioBalance,
            'levels' => $levels,
            'buckets' => array_values($buckets),
        ];
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
            'aging' => $loan->currentAging($referenceDate),
        ];
    }
}
