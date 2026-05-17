<?php

namespace App\Filament\Resources\Collaterals\Schemas;

use App\Support\CurrencyHelper;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CollateralForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Collateral Information')
                    ->description('Details of the assets provided as security for the loan.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                Select::make('loan_id')
                                    ->relationship('loan', 'loan_code')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Associated Loan')
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (!$state) return;
                                        
                                        $loan = \App\Models\Loan::find($state);
                                        if ($loan && $loan->start_date) {
                                            $set('start_date', $loan->start_date);
                                        }
                                    }),
                                TextInput::make('type')
                                    ->required()
                                    ->placeholder('e.g. Real Estate, Vehicle, Equipment')
                                    ->label('Collateral Type'),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'Held' => 'Held (In Institution)',
                                        'Returned' => 'Returned (To Client)',
                                        'Liquidating' => 'Liquidating (Defaulted)',
                                    ])
                                    ->default('Held')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state === 'Returned') {
                                            $set('end_date', now()->format('Y-m-d'));
                                        } elseif ($state === 'Held') {
                                            $set('end_date', null);
                                        }
                                    }),
                                TextInput::make('certificate_number')
                                    ->label('Certificate Number')
                                    ->placeholder('e.g. Title Deed No, Registration No'),
                                TextInput::make('license_plate')
                                    ->label('License Plate')
                                    ->placeholder('e.g. PP-1234'),
                                TextInput::make('owner_name')
                                    ->required()
                                    ->label('Owner Name'),
                                Grid::make(2)
                                    ->schema([
                                        \Filament\Forms\Components\DatePicker::make('start_date')
                                            ->label('Start Date')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),
                                        \Filament\Forms\Components\DatePicker::make('end_date')
                                            ->label('End Date')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('value')
                                            ->numeric()
                                            ->required()
                                            ->label('Estimated Value'),
                                        Select::make('currency')
                                            ->options(CurrencyHelper::options())
                                            ->default(CurrencyHelper::USD)
                                            ->required(),
                                    ]),
                                Textarea::make('description')
                                    ->columnSpanFull()
                                    ->rows(3)
                                    ->placeholder('Detailed description of the collateral...'),
                            ]),
                    ]),
            ]);
    }
}
