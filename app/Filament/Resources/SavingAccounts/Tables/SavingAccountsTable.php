<?php

namespace App\Filament\Resources\SavingAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SavingAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_number')
                    ->label('Ref No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('saver.id')
                    ->label('Client')
                    ->getStateUsing(fn($record) => "{$record->saver?->last_name} {$record->saver?->first_name}")
                    ->searchable(['saver.first_name', 'saver.last_name'])
                    ->sortable(),
                TextColumn::make('account_type')
                    ->label('Plan')
                    ->badge(),
                TextColumn::make('balance')
                    ->label('Outstanding')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Active' => 'success',
                        'Dormant' => 'warning',
                        'Closed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('category')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Real Capital' => 'success',
                        'Loan Capital' => 'info',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('account_type')
                    ->options([
                        'Daily Saving' => 'Short term',
                        'Goal Saving' => 'Mid term',
                        'Fixed Deposit' => 'Long term',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Dormant' => 'Dormant',
                        'Closed' => 'Closed',
                    ]),
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
