<?php

namespace App\Filament\Resources\Loans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
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
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('loan_code')
                    ->label('Loan')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record): ?string => collect([
                        filled($record->start_date) ? 'Start ' . self::formatDate($record->start_date) : null,
                        filled($record->loan_cycle) ? 'Cycle ' . $record->loan_cycle : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('borrower.first_name')
                    ->label('Borrower')
                    ->getStateUsing(fn($record) => trim("{$record->borrower?->last_name} {$record->borrower?->first_name}"))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->description(fn($record): ?string => collect([
                        filled($record->officer?->name) ? 'Officer ' . $record->officer->name : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('amount')
                    ->label('Principal')
                    ->money(fn($record): string => self::resolveCurrencyCode($record))
                    ->sortable()
                    ->description(fn($record): ?string => collect([
                        filled($record->interest_rate) ? 'Rate ' . self::formatNumber((float) $record->interest_rate) . '%' : null,
                        filled($record->duration_months) ? $record->duration_months . ' mo' : null,
                        filled($record->monthly_payment)
                        ? 'Pay ' . self::formatAmount((float) $record->monthly_payment, self::resolveCurrencyCode($record))
                        : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('interest_rate')
                    ->label('Rate')
                    ->suffix('%')
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('duration_months')
                    ->label('Term')
                    ->suffix(' mo')
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('start_date')
                    ->date('d M Y')
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
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
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'paid_off' => 'Paid off',
                        'written_off' => 'Written off',
                    ]),
                SelectFilter::make('currency')
                    ->options([
                        'USD' => 'USD',
                        'KHR' => 'KHR',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Manage Loan')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Manage loan'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Loan')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    private static function resolveCurrencyCode(object $record): string
    {
        return str_starts_with(strtoupper((string) $record->currency), 'KHR') ? 'KHR' : 'USD';
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

    private static function formatNumber(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private static function formatAmount(float $amount, string $currency): string
    {
        return $currency === 'KHR'
            ? 'KHR ' . number_format($amount, 0)
            : '$' . number_format($amount, 2);
    }
}
