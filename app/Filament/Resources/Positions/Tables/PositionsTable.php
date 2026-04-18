<?php

namespace App\Filament\Resources\Positions\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Positions')
            ->description('Define and manage organization roles, departments, and base salary scales.')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->code) ? $record->code : null,
                        filled($record->department) ? $record->department : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('code')
                    ->searchable()
                    ->visibleFrom('xl'),
                TextColumn::make('department')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('base_salary')
                    ->money(fn ($record): string => CurrencyHelper::normalize($record->currency ?? CurrencyHelper::USD))
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->type) ? $record->type : null,
                        filled($record->reportingTo?->name) ? 'Reports to ' . $record->reportingTo->name : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('currency')
                    ->formatStateUsing(fn ($state): string => CurrencyHelper::normalize($state))
                    ->visibleFrom('2xl'),
                TextColumn::make('reportingTo.name')
                    ->label('Reports To')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
                SelectFilter::make('currency')
                    ->options(CurrencyHelper::options()),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage position'),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Position')
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
