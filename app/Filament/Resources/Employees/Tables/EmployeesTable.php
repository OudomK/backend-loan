<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
            ->persistFiltersInSession()
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
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->employee_code) ? $record->employee_code : null,
                        filled($record->phone) ? $record->phone : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('employee_code')
                    ->label('Code')
                    ->searchable()
                    ->visibleFrom('xl'),
                TextColumn::make('position.name')
                    ->label('Position')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->employment_type) ? $record->employment_type : null,
                        filled($record->date_joined) ? 'Start ' . self::formatDate($record->date_joined) : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'resigned' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('salary')
                    ->money(fn ($record): string => CurrencyHelper::normalize($record->currency ?? CurrencyHelper::USD))
                    ->sortable()
                    ->description(fn ($record): ?string => filled($record->currency)
                        ? CurrencyHelper::normalize((string) $record->currency)
                        : null),
                TextColumn::make('currency')
                    ->formatStateUsing(fn ($state): string => CurrencyHelper::normalize($state))
                    ->visibleFrom('2xl'),
                TextColumn::make('phone')
                    ->searchable()
                    ->visibleFrom('2xl'),
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
                SelectFilter::make('currency')
                    ->options(CurrencyHelper::options()),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage employee'),
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

    private static function formatDate(?string $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
