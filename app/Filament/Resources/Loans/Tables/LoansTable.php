<?php

namespace App\Filament\Resources\Loans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Loan Portfolio')
            ->description('Review disbursements, interest terms, and repayment status across all loan records.')
            ->defaultSort('start_date', 'desc')
            ->persistSortInSession()
            ->striped()
            ->columns([
                TextColumn::make('loan_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('borrower.first_name')
                    ->label('Borrower')
                    ->getStateUsing(fn($record) => "{$record->borrower?->last_name} {$record->borrower?->first_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),
                TextColumn::make('amount')
                    ->money(fn($record): string => str_starts_with(strtoupper((string) $record->currency), 'KHR') ? 'KHR' : 'USD')
                    ->sortable(),
                TextColumn::make('interest_rate')
                    ->label('Rate')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('duration_months')
                    ->label('Duration')
                    ->suffix(' mo')
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'active' => 'success',
                        'completed' => 'info',
                        'paid_off' => 'success',
                        'written_off' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'paid_off' => 'Paid off',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Manage')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->button(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Loan')
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
