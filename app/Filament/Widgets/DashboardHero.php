<?php

namespace App\Filament\Widgets;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use App\Models\Setting;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardHero extends Widget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.dashboard-hero';

    protected function getViewData(): array
    {
        $ttl = 60;

        return [
            'todayLabel' => now()->format('l, d M Y'),
            'dueToday' => Cache::remember('filament.dashboard.hero.due_today', $ttl, function () {
                $today = now()->toDateString();

                return Loan::query()
                    ->where('status', 'active')
                    ->whereHas('payments', function ($query) use ($today) {
                        $query
                            ->whereDate('payment_date', $today)
                            ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)');
                    })
                    ->count();
            }),
            'overdueInstallments' => Cache::remember(
                'filament.dashboard.hero.overdue_installments',
                $ttl,
                fn () => Payment::query()
                    ->whereHas('loan', fn ($q) => $q->where('status', 'active'))
                    ->whereDate('payment_date', '<', now()->toDateString())
                    ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
                    ->count(),
            ),
            'par30' => Cache::remember(
                'filament.dashboard.hero.par30',
                $ttl,
                fn () => $this->calculatePar30(),
            ),
            'todayCollections' => Cache::remember(
                'filament.dashboard.hero.today_collections',
                $ttl,
                fn () => (float) RepaymentTransaction::whereDate('transaction_date', today())->sum('amount_paid'),
            ),
        ];
    }

    private function calculatePar30(): float
    {
        $exchangeRate = (float) (
            Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
            ?? Setting::where('key', 'exchange_rate')->value('value')
            ?? 4000
        );
        $exchangeRate = max(1, $exchangeRate);

        $outstandingUsd = $this->calculateOutstandingByCurrency('USD%');
        $outstandingKhr = $this->calculateOutstandingByCurrency('KHR%');
        $totalOutstanding = $outstandingUsd + ($outstandingKhr / $exchangeRate);

        if ($totalOutstanding <= 0) {
            return 0.0;
        }

        $loanIdsOverdue30 = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', '!=', 'completed')
            ->where('payments.payment_date', '<', now()->subDays(30)->toDateString())
            ->whereRaw('COALESCE(payments.total_paid,0) < (COALESCE(payments.principal_amount,0) + COALESCE(payments.interest_amount,0))')
            ->distinct()
            ->pluck('loans.id');

        if ($loanIdsOverdue30->isEmpty()) {
            return 0.0;
        }

        $parOutstandingUsd = $this->calculateOutstandingByCurrency('USD%', $loanIdsOverdue30->all());
        $parOutstandingKhr = $this->calculateOutstandingByCurrency('KHR%', $loanIdsOverdue30->all());
        $parOutstanding = $parOutstandingUsd + ($parOutstandingKhr / $exchangeRate);

        return round(($parOutstanding / $totalOutstanding) * 100, 2);
    }

    private function calculateOutstandingByCurrency(string $currencyLike, ?array $loanIds = null): float
    {
        $loanQuery = Loan::query()
            ->where('status', '!=', 'completed')
            ->where('currency', 'LIKE', $currencyLike);

        if (! empty($loanIds)) {
            $loanQuery->whereIn('id', $loanIds);
        }

        $disbursed = (float) $loanQuery->sum('amount');

        $principalPaidQuery = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', '!=', 'completed')
            ->where('loans.currency', 'LIKE', $currencyLike)
            ->selectRaw('COALESCE(SUM(LEAST(COALESCE(payments.principal_amount,0), GREATEST(0, COALESCE(payments.total_paid,0) - COALESCE(payments.interest_amount,0)))), 0) as paid');

        if (! empty($loanIds)) {
            $principalPaidQuery->whereIn('loans.id', $loanIds);
        }

        $principalPaid = (float) ($principalPaidQuery->value('paid') ?? 0);

        return max(0, $disbursed - $principalPaid);
    }
}
