<?php

namespace App\Filament\Resources\RepaymentTransactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class RepaymentTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('loan.loan_code')
                    ->label('Loan Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('loan.borrower.id')
                    ->label('Borrower')
                    ->getStateUsing(fn($record) => "{$record->loan?->borrower?->first_name} {$record->loan?->borrower?->last_name}")
                    ->searchable(['loan.borrower.first_name', 'loan.borrower.last_name'])
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('principal_paid')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('interest_paid')
                    ->money('USD')
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
                Filter::make('transaction_date'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
