<?php

namespace App\Filament\Resources\CoBorrowers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CoBorrowersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Co-Borrowers')
            ->description('Create, update, deactivate, blacklist, and review co-borrower records with full profile control.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('photo')
                    ->circular()
                    ->height(32)
                    ->width(32)
                    ->defaultImageUrl(function ($record) {
                        $colors = ['0d9488', '4f46e5', 'e11d48', 'd97706', '059669', '7c3aed', '0891b2', '2563eb', 'db2777', '7c2d12'];
                        $color = $colors[$record->id % count($colors)];
                        return "https://ui-avatars.com/api/?name=" . urlencode("{$record->last_name} {$record->first_name}") . "&color=FFFFFF&background={$color}";
                    }),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn($record) => "{$record->last_name} {$record->first_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('customer_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('id_number')
                    ->label('ID Number')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('gender')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('marital_status')
                    ->label('Marital')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('occupation')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
            ->headerActions([
                CreateAction::make()
                    ->label('New Co-Borrower')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
