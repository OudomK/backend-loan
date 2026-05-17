<?php

namespace App\Filament\Resources\ExpenseCategories\Tables;

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

class ExpenseCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Expense Categories')
            ->description('Define and manage expense classification categories used in miscellaneous transactions and income statements.')
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
                    ->description(fn($record): ?string => filled($record->description)
                        ? \Illuminate\Support\Str::limit((string) $record->description, 50)
                        : null),
                TextColumn::make('group_name')
                    ->label('Group')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'Administrative Expenses' => 'info',
                        'Operating Expenses' => 'warning',
                        'Selling & Marketing Expenses' => 'success',
                        'Finance Costs' => 'danger',
                        'Other Operating Expenses' => 'gray',
                        'Miscellaneous Expense' => 'gray',
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
                    ->description(fn($record): ?string => $record->updated_at?->format('d/m/Y')),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                SelectFilter::make('group_name')
                    ->label('Group')
                    ->options([
                        'Administrative Expenses' => 'Administrative Expenses',
                        'Operating Expenses' => 'Operating Expenses',
                        'Selling & Marketing Expenses' => 'Selling & Marketing Expenses',
                        'Finance Costs' => 'Finance Costs',
                        'Other Operating Expenses' => 'Other Operating Expenses',
                        'Miscellaneous Expense' => 'Miscellaneous Expense',
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
                    ->label('New Expense Category')
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
