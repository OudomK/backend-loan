<?php

namespace App\Filament\Resources\MiscellaneousTransactions\Schemas;

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
                Section::make('Transaction Details')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('type')
                                    ->options([
                                        'revenue' => 'Revenue',
                                        'expense' => 'Expense',
                                        'Income' => 'Revenue (Legacy)',
                                        'Expense' => 'Expense (Legacy)',
                                    ])
                                    ->dehydrateStateUsing(function ($state): string {
                                        $normalized = strtolower((string) $state);

                                        return $normalized === 'income' ? 'revenue' : $normalized;
                                    })
                                    ->helperText('Use Revenue/Expense so reports are calculated correctly.')
                                    ->required(),
                                TextInput::make('category')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('e.g. Rent, Utilities, Fees'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix('$'),
                                TextInput::make('currency')
                                    ->default('USD')
                                    ->required(),
                                DatePicker::make('transaction_date')
                                    ->default(now())
                                    ->required()
                                    ->native(false),
                            ]),
                        Textarea::make('description')
                            ->columnSpanFull()
                            ->maxLength(65535),
                    ]),
            ]);
    }
}
