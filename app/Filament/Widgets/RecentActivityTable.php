<?php

namespace App\Filament\Widgets;

use App\Models\RepaymentTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class RecentActivityTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Recent Repayment Activity';
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Repayment Activity')
            ->description('Latest 5 repayment transactions across all officers')
            ->emptyStateHeading('No recent repayments')
            ->emptyStateDescription('Repayment transactions will appear here once they are recorded.')
            ->query(function () {
                return RepaymentTransaction::query()
                    ->with(['loan.borrower'])
                    ->latest('transaction_date')
                    ->limit(5);
            })
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('loan.loan_code')
                    ->label('Loan Code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan.borrower.full_name')
                    ->label('Borrower')
                    ->getStateUsing(fn($record) => ($record->loan->borrower->first_name ?? '') . ' ' . ($record->loan->borrower->last_name ?? '')),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, $record): string {
                        $amount = (float) $state;
                        $isKhr = str_starts_with((string) ($record->loan->currency ?? ''), 'KHR');

                        return $isKhr
                            ? '៛ ' . number_format($amount, 0)
                            : '$' . number_format($amount, 2);
                    })
                    ->color('success')
                    ->alignEnd()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('repayment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pay Off' => 'success',
                        'Withdraw' => 'danger',
                        'Prepayment' => 'warning',
                        default => 'info',
                    }),
            ])
            ->paginated(false);
    }
}
