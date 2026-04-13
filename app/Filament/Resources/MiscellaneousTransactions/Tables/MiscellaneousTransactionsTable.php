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
            ->description('Create, review, edit, and soft-delete miscellaneous revenue and expense records with proper currency tracking.')
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d/m/Y')
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
                    ->label('Category / Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currency')
                    ->toggleable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record): string => trim(sprintf(
                        '%s %s',
                        strtoupper((string) ($record->currency ?? 'USD')),
                        number_format((float) $state, 2)
                    )))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description / Memo')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'revenue' => 'Revenue',
                        'expense' => 'Expense',
                    ]),
                SelectFilter::make('currency')
                    ->options([
                        'USD' => 'USD',
                        'KHR' => 'KHR',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
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
