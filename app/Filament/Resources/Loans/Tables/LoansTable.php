<?php

namespace App\Filament\Resources\Loans\Tables;

use App\Support\CurrencyHelper;
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
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['borrower', 'officer', 'product']))
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
                    ->getStateUsing(fn($record) => trim("{$record->borrower?->first_name} {$record->borrower?->last_name}"))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->description(fn($record): ?string => collect([
                        filled($record->officer?->name) ? 'Officer ' . $record->officer->name : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->label('Principal')
                    ->formatStateUsing(fn ($state, $record) => CurrencyHelper::display(
                        (float) $state,
                        $record->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->sortable()
                    ->description(fn($record): ?string => collect([
                        filled($record->interest_rate) ? 'Rate ' . self::formatNumber((float) $record->interest_rate) . '%' : null,
                        filled($record->duration_months) ? $record->duration_months . ' ' . self::termUnitAbbreviation($record->payment_frequency) : null,
                        filled($record->monthly_payment)
                        ? 'Pay ' . CurrencyHelper::display(
                            (float) $record->monthly_payment,
                            $record->currency ?? CurrencyHelper::USD,
                        )
                        : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('interest_rate')
                    ->label('Rate')
                    ->suffix('%')
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('duration_months')
                    ->label('Term')
                    ->formatStateUsing(fn($state, $record) => filled($state) ? $state . ' ' . self::termUnitAbbreviation($record->payment_frequency) : null)
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
                SelectFilter::make('product_id')
                    ->relationship('product', 'name')
                    ->label('Product')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Manage Loan')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Manage loan'),
                \Filament\Actions\DeleteAction::make(),
                \Filament\Actions\ForceDeleteAction::make(),
                \Filament\Actions\RestoreAction::make(),
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

    private static function termUnitAbbreviation(?string $paymentFrequency): string
    {
        $normalized = strtolower(trim((string) $paymentFrequency));

        return match ($normalized) {
            'monthly' => 'mo',
            'biweekly' => 'biwk',
            'weekly' => 'wk',
            'daily' => 'd',
            'term' => 'inst',
            default => 'mo',
        };
    }
}
