<?php

namespace App\Filament\Resources\CapitalShares\Schemas;

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
                                    ->label('Investor')
                                    ->relationship('lender', 'first_name')
                                    ->searchable()
                                    ->required(),
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
                            ]),
                    ]),
            ]);
    }
}
