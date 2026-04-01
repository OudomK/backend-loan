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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MiscellaneousTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
