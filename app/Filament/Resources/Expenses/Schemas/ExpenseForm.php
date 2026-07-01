<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expense Information')
                    ->description('General information about the expense')
                    ->schema([
                        Select::make('expense_category_id')
                            ->label('Expense Category')
                            ->relationship('expenseCategory', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->prefixIcon('heroicon-o-tag'),
                        
                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->prefixIcon('heroicon-o-currency-dollar'),
                                
                                Select::make('currency')
                                    ->options([
                                        'USD' => 'USD',
                                        'KHR' => 'KHR',
                                    ])
                                    ->default('USD')
                                    ->required(),
                                
                                DatePicker::make('transaction_date')
                                    ->label('Date')
                                    ->default(now())
                                    ->required()
                                    ->prefixIcon('heroicon-o-calendar'),
                            ]),
                        
                        Grid::make(2)
                            ->schema([
                                TextInput::make('reference_no')
                                    ->label('Reference No')
                                    ->prefixIcon('heroicon-o-hashtag'),
                                
                                Select::make('payment_method')
                                    ->options(\App\Models\PaymentMethod::where('is_active', true)->pluck('name', 'name')->toArray())
                                    ->default('Cash')
                                    ->prefixIcon('heroicon-o-wallet'),
                            ]),

                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                
                Hidden::make('created_by')
                    ->default(fn () => \Illuminate\Support\Facades\Auth::id()),
            ]);
    }
}
