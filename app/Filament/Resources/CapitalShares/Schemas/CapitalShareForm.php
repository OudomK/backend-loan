<?php

namespace App\Filament\Resources\CapitalShares\Schemas;

use Filament\Forms\Components\DatePicker;
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
                                    ->maxLength(100),
                                Select::make('lender_id') // Model Label: Investor
                                    ->label('Lender')
                                    ->relationship('lender', 'name')
                                    ->searchable(),
                                Select::make('investor_id')
                                    ->label('Investor')
                                    ->relationship('investor', 'first_name')
                                    ->searchable(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('category')
                                    ->options([
                                        'Regular' => 'Regular Share',
                                        'Premium' => 'Premium Share',
                                    ])
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Closed' => 'Closed',
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
                                    ->required(),
                                TextInput::make('par_value')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                TextInput::make('total_capital')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->placeholder('Auto-calculated'),
                                TextInput::make('certificate_no')
                                    ->label('Certificate No'),
                                DatePicker::make('purchase_date')
                                    ->native(false),
                                TextInput::make('currency')
                                    ->default('USD'),
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

                Section::make('Legacy / Additional Details')
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
                                TextInput::make('payment_method'),
                                DatePicker::make('first_pay_date')
                                    ->native(false),
                                TextInput::make('term_months')
                                    ->numeric(),
                                TextInput::make('amount')
                                    ->numeric()
                                    ->prefix('$'),
                                TextInput::make('interest_rate')
                                    ->numeric()
                                    ->suffix('%'),
                                TextInput::make('int_pay_mode'),
                                TextInput::make('fee')
                                    ->numeric()
                                    ->prefix('$'),
                                DatePicker::make('maturity_date')
                                    ->native(false),
                                TextInput::make('sl_term'),
                                Select::make('holder_id')
                                    ->options([
                                        // Dynamic options could be added here if needed
                                    ]),
                                TextInput::make('repayment_schedule')
                                    ->columnSpanFull()
                                    ->placeholder('JSON Structure'),
                            ]),
                    ]),
            ]);
    }
}
