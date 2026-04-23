<?php

namespace App\Filament\Resources\BorrowingRepayments\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BorrowingRepaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Borrowing Repayments')
            ->description('Track principal, interest, and penalty payments for borrowing accounts.')
            ->defaultSort('payment_date', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->description(fn($record): ?string => collect([
                        filled($record->receipt_no) ? 'Receipt ' . $record->receipt_no : null,
                        filled($record->borrowing?->account_no) ? 'Acc ' . $record->borrowing->account_no : null,
                    ])->filter()->implode(' • '))
                    ->sortable(),
                TextColumn::make('receipt_no')
                    ->label('Receipt #')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('borrowing.account_no')
                    ->label('Borrowing Account')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record): ?string => $record->borrowing?->lender?->name)
                    ->visibleFrom('lg'),
                TextColumn::make('borrowing.currency')
                    ->label('Currency')
                    ->formatStateUsing(fn($state): string => CurrencyHelper::normalize($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('principal_paid')
                    ->label('Principal')
                    ->formatStateUsing(fn($state, $record): string => CurrencyHelper::format(
                        $state,
                        $record->borrowing?->currency,
                        true,
                        2
                    ))
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('interest_paid')
                    ->label('Interest')
                    ->formatStateUsing(fn($state, $record): string => CurrencyHelper::format(
                        $state,
                        $record->borrowing?->currency,
                        true,
                        2
                    ))
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('penalty_paid')
                    ->label('Penalty')
                    ->formatStateUsing(fn($state, $record): string => CurrencyHelper::format(
                        $state,
                        $record->borrowing?->currency,
                        true,
                        2
                    ))
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->formatStateUsing(fn($state, $record): string => CurrencyHelper::format(
                        $state,
                        $record->borrowing?->currency,
                        true,
                        2
                    ))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('balance_after_payment')
                    ->label('Balance After')
                    ->formatStateUsing(fn($state, $record): string => CurrencyHelper::format(
                        $state,
                        $record->borrowing?->currency,
                        true,
                        2
                    ))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('2xl'),
                TextColumn::make('payment_method')
                    ->badge()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('reference_no')
                    ->label('Reference')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('receivedByUser.name')
                    ->label('Received By')
                    ->toggleable()
                    ->visibleFrom('xl'),
                TextColumn::make('remarks')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options([
                        'Cash' => 'Cash',
                        'Bank Transfer' => 'Bank Transfer',
                        'Cheque' => 'Cheque',
                    ]),
                SelectFilter::make('payment_status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'void' => 'Void',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit repayment'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Borrowing Repayment')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([]);
    }
}
