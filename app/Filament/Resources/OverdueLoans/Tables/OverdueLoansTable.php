<?php

namespace App\Filament\Resources\OverdueLoans\Tables;

use App\Filament\Resources\Loans\LoanResource;
use App\Support\CurrencyHelper;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OverdueLoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Overdue Payments')
            ->description('Monitor and manage overdue loan installments actively.')
            ->defaultSort('payment_date', 'asc')
            ->columns([
                TextColumn::make('loan.borrower.name')
                    ->label('Customer')
                    ->getStateUsing(fn($record) => trim("{$record->loan?->borrower?->first_name} {$record->loan?->borrower?->last_name}"))
                    ->description(fn($record): ?string => collect([
                        $record->loan?->loan_code,
                        $record->loan?->borrower?->phone,
                    ])->filter()->implode(' | '))
                    ->searchable(['loan.borrower.first_name', 'loan.borrower.last_name', 'loan.borrower.phone', 'loan.loan_code'])
                    ->weight('bold'),

                TextColumn::make('payment_date')
                    ->label('Due Date')
                    ->date('d/m/Y')
                    ->description(fn($record): string => "Installment #{$record->payment_number}")
                    ->sortable(),

                TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::display(
                        (float) $state,
                        $record->loan?->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('interest_amount')
                    ->label('Interest')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::display(
                        (float) $state,
                        $record->loan?->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('penalty')
                    ->label('Penalty')
                    ->getStateUsing(fn ($record) => $record->loan?->currentPenaltyDue() ?? 0.0)
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::display(
                        (float) $state,
                        $record->loan?->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->color('warning')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->getStateUsing(function ($record): float {
                        $loan = $record->loan;
                        if (! $loan) {
                            return 0.0;
                        }

                        $overdueInstallments = (float) $loan->payments()
                            ->where('payment_date', '<', \Carbon\Carbon::today()->toDateString())
                            ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
                            ->selectRaw('COALESCE(SUM(principal_amount + interest_amount + COALESCE(fee_amount, 0) - total_paid), 0) AS total')
                            ->value('total');

                        return max(0, $overdueInstallments + $loan->currentPenaltyDue());
                    })
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::display(
                        (float) $state,
                        $record->loan?->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->weight('bold')
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('aging')
                    ->label('Aging')
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state > 90 => 'danger',
                        $state > 30 => 'warning',
                        default => 'gray',
                    })
                    ->getStateUsing(fn ($record) => $record->loan?->currentAging() ?? 0)
                    ->formatStateUsing(fn($state) => "{$state} Days Late")
                    ->alignEnd(),
            ])
            ->filters([
                // Add filters if needed
            ])
            ->recordActions([
                Action::make('openLoan')
                    ->iconButton()
                    ->icon('heroicon-m-eye')
                    ->tooltip('Open Loan')
                    ->url(fn($record) => filled($record->loan_id) ? LoanResource::getUrl('edit', ['record' => $record->loan_id]) : null)
                    ->visible(fn($record) => filled($record->loan_id)),
                Action::make('Call')
                    ->iconButton()
                    ->icon('heroicon-m-phone')
                    ->tooltip('Call Customer')
                    ->color('success')
                    ->url(fn($record) => ($phone = $record->loan?->borrower?->phone) ? "tel:{$phone}" : null)
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }
}
