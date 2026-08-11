<?php

namespace App\Filament\Resources\Collaterals\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class CollateralsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Collateral Management')
            ->description('Track and manage assets provided as security for loans, including land titles, vehicles, and other valuable items.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('loan.loan_code')
                    ->label('Loan Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('loan.borrower.last_name')
                    ->label('Client')
                    ->getStateUsing(fn ($record) => $record->loan->borrower ? "{$record->loan->borrower->first_name} {$record->loan->borrower->last_name}" : '-')
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('loan.borrower', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('certificate_number')
                    ->label('Certificate No')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('license_plate')
                    ->label('License Plate')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('owner_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('value')
                    ->formatStateUsing(fn ($record) => CurrencyHelper::format($record->value, $record->currency))
                    ->sortable()
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Held' => 'success',
                        'Returned' => 'info',
                        'Liquidating' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(fn () => \App\Models\Collateral::whereNotNull('type')->where('type', '!=', '')->distinct()->pluck('type', 'type')->toArray()),
                SelectFilter::make('status')
                    ->options([
                        'Held' => 'Held',
                        'Returned' => 'Returned',
                        'Liquidating' => 'Liquidating',
                    ]),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip('View collateral'),
                EditAction::make()
                    ->iconButton()
                    ->color('warning')
                    ->tooltip('Edit collateral'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete collateral'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('New Collateral')
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
