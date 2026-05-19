<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class UpcomingDuePayments extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Upcoming Due Payments';
    protected static ?int $sort = 9;
    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Upcoming Due Payments')
            ->description('Installments due in the next 7 days')
            ->emptyStateHeading('No upcoming payments')
            ->emptyStateDescription('All payments are up to date for the next 7 days.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->query(function () {
                return Payment::query()
                    ->with(['loan.borrower'])
                    ->whereBetween('payment_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                    ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
                    ->orderBy('payment_date', 'asc')
                    ->limit(10);
            })
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Due Date')
                    ->date('M d')
                    ->sortable()
                    ->color(fn ($record) => \Carbon\Carbon::parse($record->payment_date)->isToday() ? 'warning' : 'gray')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('loan.loan_code')
                    ->label('Loan')
                    ->searchable()
                    ->limit(12),

                Tables\Columns\TextColumn::make('loan.borrower')
                    ->label('Borrower')
                    ->getStateUsing(fn ($record) => ($record->loan->borrower->first_name ?? '') . ' ' . ($record->loan->borrower->last_name ?? ''))
                    ->limit(18),

                Tables\Columns\TextColumn::make('total_due')
                    ->label('Amount Due')
                    ->formatStateUsing(function ($state, $record) {
                        $amount = (float) $state;
                        $isKhr = str_starts_with((string) ($record->loan->currency ?? ''), 'KHR');
                        return $isKhr
                            ? '៛ ' . number_format($amount, 0)
                            : '$' . number_format($amount, 2);
                    })
                    ->alignEnd()
                    ->weight('bold')
                    ->color('warning'),
            ])
            ->paginated(false);
    }
}
