<?php

namespace App\Filament\Resources\RepaymentTransactions\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RepaymentTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Repayments')
            ->description('Log and track all incoming loan repayments and transaction history.')
            ->defaultSort('transaction_date', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('loan.loan_code')
                    ->label('Loan')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record): ?string => collect([
                        filled($record->collector?->name) ? 'Credit Officer ' . $record->collector->name : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('loan.borrower.id')
                    ->label('Borrower')
                    ->getStateUsing(fn($record) => trim("{$record->loan?->borrower?->first_name} {$record->loan?->borrower?->last_name}"))
                    ->searchable(['loan.borrower.first_name', 'loan.borrower.last_name'])
                    ->sortable()
                    ->description(fn($record): ?string => collect([
                        filled($record->payment_method) ? $record->payment_method : null,
                        filled($record->transaction_date) ? self::formatDate($record->transaction_date) : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('amount_paid')
                    ->label('Total Paid')
                    ->getStateUsing(fn($record): float => round(
                        ((string) $record->repayment_type === 'Withdraw' ? -(float) $record->amount_paid : (float) $record->amount_paid)
                        + (float) $record->penalty_paid
                        + (float) $record->fee_paid,
                        2
                    ))
                    ->formatStateUsing(fn($state, $record) => CurrencyHelper::display(
                        (float) $state,
                        $record->loan?->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('principal_paid')
                    ->formatStateUsing(fn($state, $record) => CurrencyHelper::display(
                        (float) $state,
                        $record->loan?->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('interest_paid')
                    ->formatStateUsing(fn($state, $record) => CurrencyHelper::display(
                        (float) $state,
                        $record->loan?->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('penalty_paid')
                    ->label('Penalty')
                    ->formatStateUsing(fn($state, $record) => CurrencyHelper::display(
                        (float) $state,
                        $record->loan?->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('waived_amount')
                    ->label('Waived')
                    ->formatStateUsing(fn($state, $record) => CurrencyHelper::display(
                        (float) $state,
                        $record->loan?->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('repayment_type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Normal' => 'gray',
                        'Partial' => 'warning',
                        'Prepayment' => 'info',
                        'Pay Off' => 'success',
                        'Recovery' => 'success',
                        'Withdraw' => 'danger',
                        'Refinance' => 'primary',
                        'Reschedule' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method')
                    ->badge()
                    ->searchable(),
                TextColumn::make('transaction_date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('collector.name')
                    ->label('Credit Officer')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created On')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options(\App\Models\PaymentMethod::where('is_active', true)->pluck('name', 'name')->toArray()),
                SelectFilter::make('repayment_type')
                    ->options([
                        'Normal' => 'Normal',
                        'Prepayment' => 'Prepayment',
                        'Partial' => 'Partial',
                        'Pay Off' => 'Pay Off',
                        'Recovery' => 'Recovery',
                        'Withdraw' => 'Withdraw',
                        'Refinance' => 'Refinance',
                        'Reschedule' => 'Reschedule',
                    ]),
                SelectFilter::make('collector')
                    ->relationship('collector', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Manage')
                    ->iconButton()
                    ->tooltip('Manage repayment'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Repayment')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([]);
    }

    private static function formatDate(?string $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
