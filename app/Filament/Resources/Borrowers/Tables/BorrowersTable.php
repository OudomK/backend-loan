<?php

namespace App\Filament\Resources\Borrowers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BorrowersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Borrowers')
            ->description('Manage your borrowers and their customer profiles.')
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
                \Filament\Tables\Filters\TrashedFilter::make(),
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
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Borrower')
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
