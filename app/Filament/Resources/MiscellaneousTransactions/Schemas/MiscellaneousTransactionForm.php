<?php

namespace App\Filament\Resources\MiscellaneousTransactions\Schemas;

use App\Models\ExpenseCategory;
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
                                    ->live()
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
                                Select::make('category')
                                    ->label('Revenue Category')
                                    ->native(false)
                                    ->required(fn (callable $get): bool => $get('type') === 'revenue')
                                    ->visible(fn (callable $get): bool => $get('type') === 'revenue')
                                    ->options(fn (): array => \App\Models\RevenueCategory::query()
                                        ->where('is_active', true)
                                        ->orderBy('sort_order')
                                        ->orderBy('name')
                                        ->pluck('name', 'name')
                                        ->toArray()),
                                Select::make('expense_category_id')
                                    ->label('Expense Category')
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(fn (callable $get): bool => $get('type') === 'expense')
                                    ->visible(fn (callable $get): bool => $get('type') === 'expense')
                                    ->options(fn (): array => ExpenseCategory::query()
                                        ->where('is_active', true)
                                        ->orderBy('sort_order')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray())
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $category = ExpenseCategory::query()->find($state);
                                        if ($category) {
                                            $set('category', $category->name);
                                        }
                                    }),
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
