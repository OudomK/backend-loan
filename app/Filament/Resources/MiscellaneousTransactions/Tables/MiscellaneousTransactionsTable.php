<?php

namespace App\Filament\Resources\MiscellaneousTransactions\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn ($record): ?string => filled($record->type)
                        ? ucfirst(strtolower((string) $record->type))
                        : null),
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
                        })
                    ->visibleFrom('xl'),
                TextColumn::make('category')
                    ->label('Category / Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => filled($record->description)
                        ? \Illuminate\Support\Str::limit((string) $record->description, 42)
                        : null),
                TextColumn::make('currency')
                    ->formatStateUsing(fn ($state): string => CurrencyHelper::normalize($state))
                    ->visibleFrom('2xl'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description / Memo')
                    ->limit(50)
                    ->searchable()
                    ->visibleFrom('xl'),
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
                    ->options(CurrencyHelper::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage transaction'),
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
