<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\LoanOfficer;
use App\Models\RepaymentTransaction;
use App\Support\CurrencyHelper;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class LoanOfficerPerformance extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Loan Officer Performance';
    protected static ?int $sort = 8;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Loan Officer Performance')
            ->description('Active portfolio summary per loan officer')
            ->emptyStateHeading('No loan officers found')
            ->emptyStateDescription('Loan officers will appear here once they are assigned loans.')
            ->query(function () {
                return LoanOfficer::query()
                    ->where('status', 'active')
                    ->withCount(['loans as active_loans_count' => fn (Builder $q) => $q->where('status', 'active')])
                    ->withSum(['loans as portfolio_usd' => fn (Builder $q) => $q->where('status', 'active')->where('currency', 'USD')], 'amount')
                    ->withSum(['loans as portfolio_khr' => fn (Builder $q) => $q->where('status', 'active')->where('currency', 'KHR')], 'amount')
                    ->withCount(['loans as overdue_loans_count' => fn (Builder $q) => $q->where('status', 'active')->where('aging', '>', 0)])
                    ->orderByDesc('active_loans_count');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Officer')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('active_loans_count')
                    ->label('Active Loans')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('portfolio')
                    ->label('Portfolio')
                    ->getStateUsing(fn ($record) => $record)
                    ->formatStateUsing(fn ($state) => CurrencyHelper::displayDual(
                        (float) ($state->portfolio_usd ?? 0),
                        (float) ($state->portfolio_khr ?? 0)
                    ))
                    ->html()
                    ->alignEnd()
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('overdue_loans_count')
                    ->label('Overdue')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => ((int) $state) > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('mtd_collections')
                    ->label('MTD Collections')
                    ->getStateUsing(function ($record) {
                        $usd = (float) RepaymentTransaction::whereMonth('transaction_date', now()->month)
                            ->whereYear('transaction_date', now()->year)
                            ->where('collector_id', $record->id)
                            ->whereHas('loan', fn ($q) => $q->where('currency', 'USD'))
                            ->sum('amount_paid');
                            
                        $khr = (float) RepaymentTransaction::whereMonth('transaction_date', now()->month)
                            ->whereYear('transaction_date', now()->year)
                            ->where('collector_id', $record->id)
                            ->whereHas('loan', fn ($q) => $q->where('currency', 'KHR'))
                            ->sum('amount_paid');
                            
                        return CurrencyHelper::displayDual($usd, $khr);
                    })
                    ->html()
                    ->alignEnd()
                    ->color('info')
                    ->weight('bold'),
            ])
            ->paginated(false);
    }
}
