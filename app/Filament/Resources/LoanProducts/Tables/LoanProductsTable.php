<?php

namespace App\Filament\Resources\LoanProducts\Tables;

use App\Support\CurrencyHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;

class LoanProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Loan Products')
            ->description('Manage loan products and their default configurations.')
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('New Loan Product')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('name')
                            ->weight('bold')
                            ->size('lg')
                            ->searchable(),
                        IconColumn::make('is_active')
                            ->boolean()
                            ->alignEnd(),
                    ]),
                    TextColumn::make('code')
                        ->color('gray')
                        ->searchable(),
                    Stack::make([
                        TextColumn::make('interest_rate')
                            ->prefix('Interest: ')
                            ->suffix('%')
                            ->sortable(),
                        TextColumn::make('fee_percentage')
                            ->prefix('Fee: ')
                            ->suffix('%')
                            ->sortable(),
                        TextColumn::make('duration_months')
                            ->prefix('Term: ')
                            ->suffix(' months')
                            ->sortable(),
                        TextColumn::make('repayment_method')
                            ->badge()
                            ->color('info')
                            ->searchable(),
                    ])->space(1)->extraAttributes(['class' => 'mt-4']),
                    
                    Split::make([
                        TextColumn::make('total_loans')
                            ->state(fn ($record) => $record->loans()->count())
                            ->description('Total Loans', position: 'above')
                            ->weight('bold')
                            ->color('gray'),
                        TextColumn::make('active_clients')
                            ->state(fn ($record) => $record->loans()->where('status', 'active')->count())
                            ->description('Active Clients', position: 'above')
                            ->weight('bold')
                            ->color('success'),
                        TextColumn::make('total_outstanding')
                            ->state(function ($record) {
                                $loans = $record->loans()->where('status', 'active')->get();
                                $outstandingUsd = 0.0;
                                $outstandingKhr = 0.0;

                                foreach ($loans as $loan) {
                                    $outstanding = (float) $loan->amount - (float) ($loan->total_paid ?? 0);
                                    if ($outstanding <= 0.01) {
                                        continue;
                                    }

                                    if (str_starts_with((string) ($loan->currency ?? 'USD'), 'KHR')) {
                                        $outstandingKhr += $outstanding;
                                    } else {
                                        $outstandingUsd += $outstanding;
                                    }
                                }

                                return CurrencyHelper::displayDualPlain(
                                    $outstandingUsd,
                                    $outstandingKhr,
                                    2,
                                    0,
                                );
                            })
                            ->description('Outstanding', position: 'above')
                            ->weight('bold')
                            ->color('danger'),
                    ])->extraAttributes(['class' => 'mt-4 border-t pt-4 border-gray-200 dark:border-gray-700']),

                ])->space(2),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
