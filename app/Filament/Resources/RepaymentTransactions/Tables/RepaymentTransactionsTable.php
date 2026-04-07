<?php

namespace App\Filament\Resources\RepaymentTransactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RepaymentTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('loan.loan_code')
                    ->label('Loan Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('loan.borrower.id')
                    ->label('Borrower')
                    ->getStateUsing(fn($record) => "{$record->loan?->borrower?->last_name} {$record->loan?->borrower?->first_name}")
                    ->searchable(['loan.borrower.first_name', 'loan.borrower.last_name'])
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->money(fn($record): string => str_starts_with(strtoupper((string) $record->loan?->currency), 'KHR') ? 'KHR' : 'USD')
                    ->sortable(),
                TextColumn::make('principal_paid')
                    ->money(fn($record): string => str_starts_with(strtoupper((string) $record->loan?->currency), 'KHR') ? 'KHR' : 'USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('interest_paid')
                    ->money(fn($record): string => str_starts_with(strtoupper((string) $record->loan?->currency), 'KHR') ? 'KHR' : 'USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->badge()
                    ->searchable(),
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('collector.name')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
