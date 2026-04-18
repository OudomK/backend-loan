<?php

namespace App\Filament\Resources\LoanOfficers\Tables;

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
use Illuminate\Database\Eloquent\Builder;

class LoanOfficersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Loan Officers')
            ->description('Create, update, deactivate, and review loan officers together with their employee link and lending authority.')
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->employee?->employee_code) ? $record->employee->employee_code : null,
                        filled($record->phone) ? $record->phone : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('employee.name')
                    ->label('Linked Employee')
                    ->searchable()
                    ->visibleFrom('xl'),
                TextColumn::make('employee.employee_code')
                    ->label('Emp Code')
                    ->searchable()
                    ->visibleFrom('2xl'),
                TextColumn::make('phone')
                    ->searchable()
                    ->visibleFrom('xl'),
                TextColumn::make('gender')
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('max_loan_amount')
                    ->label('Max Loan Amount')
                    ->formatStateUsing(fn($state): string => $state !== null ? '$' . number_format((float) $state, 2) : '-')
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => ucfirst(strtolower((string) $state)))
                    ->color(fn(?string $state): string => match (strtolower((string) $state)) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('active_loans_count')
                    ->label('Active Loans')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = strtolower((string) ($data['value'] ?? ''));
                        if ($value === '') {
                            return $query;
                        }

                        return $query->whereRaw('LOWER(status) = ?', [$value]);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage loan officer'),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Loan Officer')
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
