<?php

namespace App\Filament\Resources\SavingAccounts\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SavingAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Borrowings')
            ->description('Monitor borrowing records with the same structure used in the frontend borrowing page.')
            ->defaultSort('borrowing_date', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('borrowing_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn ($record): ?string => filled($record->account_no) ? $record->account_no : null),
                TextColumn::make('account_no')
                    ->label('Account Code')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('lender.lender_code')
                    ->label('Lender Code')
                    ->searchable()
                    ->visibleFrom('2xl'),
                TextColumn::make('lender.name')
                    ->label('Lender')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->lender?->lender_code) ? $record->lender->lender_code : null,
                        filled($record->payment_method) ? $record->payment_method : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('lender.lender_type')
                    ->label('Lender Type')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Loan Capital' => 'info',
                        'Real Capital' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('first_pay_date')
                    ->label('1st Pay Date')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currency')
                    ->formatStateUsing(fn ($state): string => CurrencyHelper::normalize($state))
                    ->visibleFrom('2xl'),
                TextColumn::make('term_months')
                    ->label('Term')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('amount')
                    ->label('Loan Amount')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->interest_rate) ? 'Rate ' . number_format((float) $record->interest_rate, 2) . '%' : null,
                        filled($record->balance)
                            ? 'Balance ' . CurrencyHelper::format(max(round((float) $record->amount - (float) ($record->principal_paid_total ?? 0), 2), 0), $record->currency, true, 2)
                            : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('interest_rate')
                    ->label('Interest Rate')
                    ->formatStateUsing(fn($state) => number_format((float) $state, 2))
                    ->suffix('%')
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('fee')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('maturity_date')
                    ->label('Maturity Date')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sl_term')
                    ->label('S/L Term')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->state(fn($record) => max(round((float) $record->amount - (float) ($record->principal_paid_total ?? 0), 2), 0))
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->visibleFrom('xl'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('late_principal')
                    ->label('Late Principal')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('loan_interest')
                    ->label('Late Interest')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transaction_no')
                    ->label('Transaction No')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('loan_account')
                    ->label('Loan Account')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contract_no')
                    ->label('Contract No')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                    ]),
                SelectFilter::make('currency')
                    ->options(CurrencyHelper::options()),
                SelectFilter::make('payment_method')
                    ->options([
                        'Balloon' => 'Balloon',
                        'Declining' => 'Declining',
                        'Negotiable' => 'Negotiable',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage borrowing'),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Borrowing')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([]);
    }
}
