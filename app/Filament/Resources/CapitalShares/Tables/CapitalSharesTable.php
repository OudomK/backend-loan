<?php

namespace App\Filament\Resources\CapitalShares\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CapitalSharesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_no')
                    ->label('A/C No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner_name')
                    ->label('Owner')
                    ->getStateUsing(fn($record) => $record->investor
                        ? "{$record->investor->last_name} {$record->investor->first_name}"
                        : ($record->lender?->name ?? '-'))
                    ->wrap(),
                TextColumn::make('category')
                    ->badge(),
                TextColumn::make('total_capital')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Active' => 'success',
                        'Withdrawn' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'Real Capital' => 'Real Capital',
                        'Loan Capital' => 'Loan Capital',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Withdrawn' => 'Withdrawn',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Capital Share')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->heading('Capital Shares')
            ->description('Manage and monitor all your capital shares in one place.');
    }
}
