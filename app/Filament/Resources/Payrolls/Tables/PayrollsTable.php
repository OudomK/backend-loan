<?php

namespace App\Filament\Resources\Payrolls\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PayrollsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Payrolls')
            ->description('Track salary runs, allowances, deductions, and payout status for each payroll period.')
            ->defaultSort('month_year', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('month_year')
                    ->label('Period')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->payment_date) ? 'Paid ' . self::formatDate($record->payment_date) : null,
                        filled($record->payment_method) ? $record->payment_method : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('employee.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->employee?->employee_code) ? $record->employee->employee_code : null,
                        filled($record->employee?->position?->name) ? $record->employee->position->name : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('total_payable')
                    ->money(fn ($record): string => CurrencyHelper::normalize($record->currency ?? $record->employee?->currency ?? CurrencyHelper::USD))
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->salary) ? 'Salary ' . self::formatAmount((float) $record->salary, CurrencyHelper::normalize($record->currency ?? $record->employee?->currency ?? CurrencyHelper::USD)) : null,
                        ((float) ($record->allowance ?? 0) > 0) ? 'Allowance ' . self::formatAmount((float) $record->allowance, CurrencyHelper::normalize($record->currency ?? $record->employee?->currency ?? CurrencyHelper::USD)) : null,
                        ((float) ($record->deduction ?? 0) > 0) ? 'Deduction ' . self::formatAmount((float) $record->deduction, CurrencyHelper::normalize($record->currency ?? $record->employee?->currency ?? CurrencyHelper::USD)) : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('currency')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::normalize($state ?? $record->employee?->currency))
                    ->visibleFrom('2xl'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method')
                    ->searchable()
                    ->visibleFrom('xl'),
                TextColumn::make('payment_date')
                    ->date()
                    ->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('employee')
                    ->relationship('employee', 'name'),
                SelectFilter::make('status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('currency')
                    ->options(CurrencyHelper::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage payroll'),
                RestoreAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Payroll')
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

    private static function formatAmount(float $amount, string $currency): string
    {
        return $currency === CurrencyHelper::KHR
            ? 'KHR ' . number_format($amount, 0)
            : '$' . number_format($amount, 2);
    }
}
