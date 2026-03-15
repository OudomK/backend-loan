<?php

namespace App\Filament\Resources\Borrowers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BorrowersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->circular(),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn($record) => "{$record->last_name} {$record->first_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('customer_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Borrower' => 'info',
                        'Saver' => 'success',
                        'Investor' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'warning',
                        'Blacklisted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('province')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_type')
                    ->options([
                        'Borrower' => 'Borrower',
                        'Saver' => 'Saver',
                        'Investor' => 'Investor',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'Blacklisted' => 'Blacklisted',
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
