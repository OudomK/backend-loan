<?php

namespace App\Filament\Resources\Dividends\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Schema;

class DividendForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Declaration Details')
                    ->columns(2)
                    ->schema([
                        Select::make('currency')
                            ->options([
                                'USD' => 'USD',
                                'KHR' => 'KHR',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                $shares = \App\Models\CapitalShare::where('currency', $state)
                                    ->where('share_qty', '>', 0)
                                    ->sum('share_qty');
                                $set('total_shares_count', round($shares, 4));
                            }),

                        TextInput::make('total_shares_count')
                            ->label('Total Active Shares')
                            ->required()
                            ->numeric()
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? rtrim(rtrim(number_format((float) $state, 4, '.', ''), '0'), '.') : null)
                            ->dehydrated(),

                        Select::make('distribution_basis')
                            ->options([
                                'total' => 'Total Amount',
                                'per_share' => 'Amount per Share',
                            ])
                            ->default('total')
                            ->required()
                            ->reactive(),

                        TextInput::make('total_amount')
                            ->label('Total Dividend Amount')
                            ->required(fn (callable $get) => $get('distribution_basis') === 'total')
                            ->visible(fn (callable $get) => $get('distribution_basis') === 'total')
                            ->numeric()
                            ->prefix('$'),

                        TextInput::make('dividend_per_share')
                            ->label('Dividend per Share / Percentage')
                            ->required(fn (callable $get) => $get('distribution_basis') === 'per_share')
                            ->visible(fn (callable $get) => $get('distribution_basis') === 'per_share')
                            ->numeric()
                            ->formatStateUsing(fn ($state) => $state ? rtrim(rtrim(number_format((float) $state, 4, '.', ''), '0'), '.') : null)
                            ->suffix('%'),

                        DatePicker::make('declared_date')
                            ->default(now())
                            ->required(),

                        DatePicker::make('payment_date'),

                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),

                Hidden::make('tax_amount')->default(0),
                Hidden::make('net_amount')->default(0),
                Hidden::make('status')->default('Draft'),
                Hidden::make('declared_by')->default(fn () => Auth::id()),
            ]);
    }
}
