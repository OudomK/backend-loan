<?php

namespace App\Filament\Resources\Employees\Tables;

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

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Employees')
            ->description('Manage staff records, positions, and contact information.')
            ->columns([
                ImageColumn::make('photo')
                    ->circular()
                    ->height(32)
                    ->width(32)
                    ->defaultImageUrl(function ($record) {
                        $colors = ['0d9488', '4f46e5', 'e11d48', 'd97706', '059669', '7c3aed', '0891b2', '2563eb', 'db2777', '7c2d12'];
                        $color = $colors[$record->id % count($colors)];
                        return "https://ui-avatars.com/api/?name=" . urlencode($record->name) . "&color=FFFFFF&background={$color}";
                    }),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee_code')
                    ->label('Code')
                    ->searchable(),
                TextColumn::make('position.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'resigned' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('salary')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->searchable(),
            ])
            ->filters([
                \Filament\Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('position')
                    ->relationship('position', 'name'),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'resigned' => 'Resigned',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Employee')
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
