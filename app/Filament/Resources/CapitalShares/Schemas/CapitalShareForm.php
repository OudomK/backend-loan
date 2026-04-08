<?php

namespace App\Filament\Resources\CapitalShares\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CapitalShareForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Share Details')
                    ->icon('heroicon-o-chart-pie')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('account_no')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(100),
                                Select::make('lender_id')
                                    ->label('Lender')
                                    ->relationship('lender', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Loan Capital')
                                    ->required(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Loan Capital')
                                    ->dehydrated(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Loan Capital'),
                                Select::make('investor_id')
                                    ->label('Investor')
                                    ->relationship('investor', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->last_name} {$record->first_name} ({$record->customer_code})")
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Real Capital')
                                    ->required(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Real Capital')
                                    ->dehydrated(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Real Capital'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('category')
                                    ->options([
                                        'Real Capital' => 'Real Capital',
                                        'Loan Capital' => 'Loan Capital',
                                    ])
                                    ->default('Real Capital')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        if ($state === 'Real Capital') {
                                            $set('lender_id', null);
                                            self::syncTotalCapital($set, $get);
                                        } else {
                                            $set('investor_id', null);
                                            $amount = (float) ($get('amount') ?: 0);
                                            $set('total_capital', round($amount, 2));
                                        }
                                    }),
                                Select::make('status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Withdrawn' => 'Withdrawn',
                                    ])
                                    ->default('Active')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Investment Values')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('share_qty')
                                    ->numeric()
                                    ->required(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Real Capital')
                                    ->live()
                                    ->afterStateUpdated(fn($state, callable $set, callable $get) => self::syncTotalCapital($set, $get)),
                                TextInput::make('par_value')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Real Capital')
                                    ->live()
                                    ->afterStateUpdated(fn($state, callable $set, callable $get) => self::syncTotalCapital($set, $get)),
                                TextInput::make('amount')
                                    ->label('Loan Amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->visible(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Loan Capital')
                                    ->required(fn(callable $get) => ($get('category') ?? 'Real Capital') === 'Loan Capital')
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $set('total_capital', round((float) ($state ?: 0), 2));
                                    }),
                                TextInput::make('total_capital')
                                    ->numeric()
                                    ->prefix('$')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->placeholder('Auto-calculated'),
                                TextInput::make('certificate_no')
                                    ->label('Certificate No')
                                    ->unique(ignoreRecord: true),
                                DatePicker::make('purchase_date')
                                    ->native(false),
                                Select::make('currency')
                                    ->options([
                                        'USD' => 'USD',
                                        'KHR' => 'KHR',
                                    ])
                                    ->default('USD')
                                    ->required(),
                                TextInput::make('dividends')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                                TextInput::make('balance')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                            ]),
                    ]),

                Section::make('Additional Details')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('transaction_no'),
                                TextInput::make('loan_account'),
                                Select::make('borrower_id')
                                    ->relationship('borrower', 'first_name')
                                    ->searchable(),
                                DatePicker::make('borrowing_date')
                                    ->native(false),
                                TextInput::make('contract_no'),
                                TextInput::make('int_pay_mode'),
                                TextInput::make('holder_id'),
                                Textarea::make('repayment_schedule')
                                    ->label('Repayment Schedule (JSON)')
                                    ->columnSpanFull()
                                    ->placeholder('[{\"period\":1,\"date\":\"2026-04-08\",\"principal\":100}]')
                                    ->rule('nullable|json')
                                    ->dehydrateStateUsing(fn($state) => blank($state) ? null : json_decode((string) $state, true))
                                    ->formatStateUsing(fn($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state),
                            ]),
                    ]),
            ]);
    }

    private static function syncTotalCapital(callable $set, callable $get): void
    {
        $quantity = (float) ($get('share_qty') ?: 0);
        $parValue = (float) ($get('par_value') ?: 0);
        $totalCapital = round($quantity * $parValue, 2);

        $set('total_capital', $totalCapital);
    }
}
