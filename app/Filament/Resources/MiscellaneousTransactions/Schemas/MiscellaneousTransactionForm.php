<?php

namespace App\Filament\Resources\MiscellaneousTransactions\Schemas;

use App\Support\CurrencyHelper;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MiscellaneousTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Miscellaneous Transaction')
                    ->description('Use the same core fields as the frontend miscellaneous transaction dialog.')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                Select::make('type')
                                    ->label('Type')
                                    ->native(false)
                                    ->options([
                                        'revenue' => 'Revenue',
                                        'expense' => 'Expense',
                                    ])
                                    ->dehydrateStateUsing(function ($state): string {
                                        $normalized = strtolower((string) $state);

                                        return $normalized === 'income' ? 'revenue' : $normalized;
                                    })
                                    ->default('expense')
                                    ->required(),
                                TextInput::make('category')
                                    ->label('Category / Name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('e.g. Rent, Utilities, Fees'),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency'))),
                                Select::make('currency')
                                    ->label('Currency')
                                    ->native(false)
                                    ->options(CurrencyHelper::options())
                                    ->default(CurrencyHelper::USD)
                                    ->live()
                                    ->required(),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                DatePicker::make('transaction_date')
                                    ->label('Transaction Date')
                                    ->default(now())
                                    ->required()
                                    ->native(false),
                            ]),
                        Textarea::make('description')
                            ->label('Description / Memo')
                            ->columnSpanFull()
                            ->maxLength(65535),
                    ]),
            ]);
    }
}
