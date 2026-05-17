<?php

namespace App\Filament\Resources\Revenues\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class RevenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Revenue Information')
                    ->description('General information about the revenue')
                    ->schema([
                        Select::make('revenue_category_id')
                            ->label('Revenue Category')
                            ->relationship('revenue_category', 'name', fn ($query) => $query->where('is_active', true))
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
                                    ->placeholder('Auto-generated if empty')
                                    ->prefixIcon('heroicon-o-hashtag'),
                                
                                Select::make('payment_method')
                                    ->options([
                                        'Cash' => 'Cash',
                                        'Bank Transfer' => 'Bank Transfer',
                                        'Cheque' => 'Cheque',
                                        'Other' => 'Other',
                                    ])
                                    ->default('Cash')
                                    ->prefixIcon('heroicon-o-wallet'),
                            ]),

                        Select::make('loan_id')
                            ->label('Related Loan')
                            ->relationship('loan', 'loan_code')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select if related to a loan')
                            ->prefixIcon('heroicon-o-document-text'),

                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
