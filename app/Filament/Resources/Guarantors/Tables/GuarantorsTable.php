<?php

namespace App\Filament\Resources\Guarantors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GuarantorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Guarantors')
            ->description('Manage loan guarantors and their guarantee details.')
            ->columns([
                ImageColumn::make('photo')
                    ->circular()
                    ->toggleable(),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn($record) => "{$record->last_name} {$record->first_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('customer_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'warning',
                        'Blacklisted' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->deferColumnManager()
            ->filters([
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
                CreateAction::make()
                    ->label('New Guarantor')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
