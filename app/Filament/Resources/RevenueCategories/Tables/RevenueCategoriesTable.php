<?php

namespace App\Filament\Resources\RevenueCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RevenueCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Revenue Categories')
            ->description('Define and manage revenue classification categories used in general revenues and financial reports.')
            ->defaultSort('sort_order')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record): ?string => filled($record->description)
                        ? \Illuminate\Support\Str::limit((string) $record->description, 50)
                        : null),
                TextColumn::make('group_name')
                    ->label('Group')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Revenue' => 'success',
                        'Operating Income' => 'info',
                        'Non-Operating Income' => 'warning',
                        'Other Revenue' => 'gray',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->updated_at?->format('d/m/Y')),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('group_name')
                    ->label('Group')
                    ->options([
                        'Revenue' => 'Revenue',
                        'Operating Income' => 'Operating Income',
                        'Non-Operating Income' => 'Non-Operating Income',
                        'Other Revenue' => 'Other Revenue',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit category'),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Revenue Category')
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
