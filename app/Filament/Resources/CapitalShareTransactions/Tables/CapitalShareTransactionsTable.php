<?php

namespace App\Filament\Resources\CapitalShareTransactions\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CapitalShareTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Capital Share Transactions')
            ->description('View add capital, withdrawal, repayment, and dividend transactions for capital share accounts.')
            ->defaultSort('transaction_date', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->description(fn($record): ?string => collect([
                        filled($record->capitalShare?->account_no) ? 'Acc ' . $record->capitalShare->account_no : null,
                        filled($record->transaction_type) ? $record->transaction_type : null,
                    ])->filter()->implode(' • '))
                    ->sortable(),
                TextColumn::make('capitalShare.account_no')
                    ->label('Account Code')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('holder_name')
                    ->label('Holder')
                    ->state(function ($record): string {
                        $share = $record->capitalShare;
                        if (!$share) {
                            return '-';
                        }

                        if ($share->investor) {
                            return trim(($share->investor->last_name ?? '') . ' ' . ($share->investor->first_name ?? ''));
                        }

                        if ($share->lender) {
                            return (string) ($share->lender->name ?? '-');
                        }

                        if ($share->borrower) {
                            return trim(($share->borrower->last_name ?? '') . ' ' . ($share->borrower->first_name ?? ''));
                        }

                        return '-';
                    })
                    ->searchable(['capitalShare.investor.first_name', 'capitalShare.investor.last_name', 'capitalShare.lender.name'])
                    ->visibleFrom('lg'),
                TextColumn::make('transaction_type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Initial' => 'info',
                        'Deposit' => 'success',
                        'Dividend' => 'success',
                        'Withdrawal' => 'danger',
                        'Repayment' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('share_qty')
                    ->label('Share Qty')
                    ->numeric()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('xl'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn($state, $record): string => CurrencyHelper::format(
                        $state,
                        $record->capitalShare?->currency,
                        true,
                        2
                    ))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('capitalShare.currency')
                    ->label('Currency')
                    ->formatStateUsing(fn($state): string => CurrencyHelper::normalize($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('reference_no')
                    ->label('Reference')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('description')
                    ->limit(45)
                    ->toggleable()
                    ->visibleFrom('xl'),
                TextColumn::make('performedByUser.name')
                    ->label('Performed By')
                    ->toggleable()
                    ->visibleFrom('2xl'),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('transaction_type')
                    ->options([
                        'Initial' => 'Initial',
                        'Deposit' => 'Deposit',
                        'Withdrawal' => 'Withdrawal',
                        'Repayment' => 'Repayment',
                        'Dividend' => 'Dividend',
                    ]),
                SelectFilter::make('payment_method')
                    ->options([
                        'Cash' => 'Cash',
                        'Bank Transfer' => 'Bank Transfer',
                        'Cheque' => 'Cheque',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit transaction'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Capital Transaction')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([]);
    }
}
