<?php

namespace App\Filament\Resources\Borrowers\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
            ->description('Create, update, deactivate, blacklist, delete, and restore borrower records with full profile control.')
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession()
            ->columns([
                ImageColumn::make('photo')
                    ->circular()
                    ->height(32)
                    ->width(32)
                    ->defaultImageUrl(function ($record) {
                        $colors = ['0d9488', '4f46e5', 'e11d48', 'd97706', '059669', '7c3aed', '0891b2', '2563eb', 'db2777', '7c2d12'];
                        $color = $colors[$record->id % count($colors)];
                        return "https://ui-avatars.com/api/?name=" . urlencode("{$record->first_name} {$record->last_name}") . "&color=FFFFFF&background={$color}";
                    }),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn ($record) => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->customer_code) ? $record->customer_code : null,
                        filled($record->phone) ? $record->phone : null,
                        filled($record->id_number) ? 'ID ' . $record->id_number : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('customer_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('phone')
                    ->searchable()
                    ->visibleFrom('xl'),
                TextColumn::make('id_number')
                    ->label('ID Number')
                    ->searchable()
                    ->visibleFrom('2xl'),
                TextColumn::make('gender')
                    ->badge()
                    ->visibleFrom('2xl'),
                TextColumn::make('marital_status')
                    ->label('Marital')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('occupation')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'warning',
                        'Blacklisted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('province')
                    ->sortable()
                    ->visibleFrom('2xl'),
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
                \Filament\Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'Blacklisted' => 'Blacklisted',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage borrower'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete borrower'),
                RestoreAction::make()
                    ->iconButton()
                    ->tooltip('Restore borrower'),
                ForceDeleteAction::make()
                    ->iconButton()
                    ->tooltip('Permanently delete borrower'),
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
