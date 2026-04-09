<?php

namespace App\Filament\Resources\MiscellaneousTransactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MiscellaneousTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Miscellaneous Transactions')
            ->description('Log and track miscellaneous revenue, expenses, and one-off transactions.')
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(function (?string $state): string {
                        return match (strtolower((string) $state)) {
                            'revenue', 'income' => 'Revenue',
                            'expense' => 'Expense',
                            default => (string) $state,
                        };
                    })
                    ->color(fn(?string $state): string => match (strtolower((string) $state)) {
                        'revenue', 'income' => 'success',
                        'expense' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'revenue' => 'Revenue',
                        'expense' => 'Expense',
                        'Income' => 'Revenue (Legacy)',
                        'Expense' => 'Expense (Legacy)',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Transaction')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
