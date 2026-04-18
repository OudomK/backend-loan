<?php

namespace App\Filament\Resources\RepaymentTransactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RepaymentTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Repayments')
            ->description('Log and track all incoming loan repayments and transaction history.')
            ->defaultSort('transaction_date', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('loan.loan_code')
                    ->label('Loan')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->collector?->name) ? 'Collector ' . $record->collector->name : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('loan.borrower.id')
                    ->label('Borrower')
                    ->getStateUsing(fn ($record) => trim("{$record->loan?->borrower?->last_name} {$record->loan?->borrower?->first_name}"))
                    ->searchable(['loan.borrower.first_name', 'loan.borrower.last_name'])
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->payment_method) ? $record->payment_method : null,
                        filled($record->transaction_date) ? self::formatDate($record->transaction_date) : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('amount_paid')
                    ->label('Total Paid')
                    ->money(fn ($record): string => self::resolveCurrencyCode($record))
                    ->sortable(),
                TextColumn::make('principal_paid')
                    ->money(fn ($record): string => self::resolveCurrencyCode($record)),
                TextColumn::make('interest_paid')
                    ->money(fn ($record): string => self::resolveCurrencyCode($record)),
                TextColumn::make('repayment_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Normal' => 'gray',
                        'Partial' => 'warning',
                        'Prepayment' => 'info',
                        'Pay Off' => 'success',
                        'Recovery' => 'success',
                        'Withdraw' => 'danger',
                        'Refinance' => 'primary',
                        'Reschedule' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method')
                    ->badge()
                    ->searchable(),
                TextColumn::make('transaction_date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('collector.name')
                    ->label('Collector')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created On')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options([
                        'Cash' => 'Cash',
                        'Bank Transfer' => 'Bank Transfer',
                        'Mobile Money' => 'Mobile Money',
                        'Cheque' => 'Cheque',
                        'Internal' => 'Internal',
                    ]),
                SelectFilter::make('repayment_type')
                    ->options([
                        'Normal' => 'Normal',
                        'Prepayment' => 'Prepayment',
                        'Partial' => 'Partial',
                        'Pay Off' => 'Pay Off',
                        'Recovery' => 'Recovery',
                        'Withdraw' => 'Withdraw',
                        'Refinance' => 'Refinance',
                        'Reschedule' => 'Reschedule',
                    ]),
                SelectFilter::make('collector')
                    ->relationship('collector', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage repayment'),
                RestoreAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Repayment')
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

    private static function resolveCurrencyCode(object $record): string
    {
        return str_starts_with(strtoupper((string) $record->loan?->currency), 'KHR') ? 'KHR' : 'USD';
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
        return $currency === 'KHR'
            ? 'KHR ' . number_format($amount, 0)
            : '$' . number_format($amount, 2);
    }
}
