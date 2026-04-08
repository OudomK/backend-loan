<?php

namespace App\Filament\Resources\Employees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                    ->circular(),
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
                        'Active' => 'success',
                        'Inactive' => 'warning',
                        'Resigned' => 'danger',
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
                SelectFilter::make('position')
                    ->relationship('position', 'name'),
                SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'Resigned' => 'Resigned',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->label('New Employee')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
