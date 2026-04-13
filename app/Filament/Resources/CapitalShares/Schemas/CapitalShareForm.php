<?php

namespace App\Filament\Resources\CapitalShares\Schemas;

use App\Filament\Resources\CapitalShares\CapitalShareResource;
use App\Models\Investor;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CapitalShareForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('category')
                    ->default('Real Capital'),

                Section::make('Capital & Share Details')
                    ->description('Use the same fields and defaults as the frontend capital/share dialog.')
                    ->icon('heroicon-o-chart-pie')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('borrowing_date')
                                    ->label('Date')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->default(fn () => Carbon::today()->toDateString())
                                    ->required(),
                                TextInput::make('account_no')
                                    ->label('Account Code')
                                    ->default(fn () => CapitalShareResource::nextAccountNo())
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(100)
                                    ->readOnly(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('investor_code_preview')
                                    ->label('Investor Code')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function (TextInput $component, $record): void {
                                        $component->state($record?->investor?->customer_code);
                                    }),
                                Select::make('investor_id')
                                    ->label('Name (Investor)')
                                    ->relationship('investor', 'first_name', fn (Builder $query) => $query->orderBy('last_name')->orderBy('first_name'))
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->last_name} {$record->first_name} ({$record->customer_code})")
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->helperText('Create investors from the Investors menu if the person is not listed yet.')
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $investor = $state ? Investor::find($state) : null;
                                        $set('investor_code_preview', $investor?->customer_code);
                                    })
                                    ->required(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('currency')
                                    ->options([
                                        'USD' => 'USD',
                                        'KHR' => 'KHR',
                                    ])
                                    ->default('USD')
                                    ->required()
                                    ->native(false),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                TextInput::make('share_qty')
                                    ->label('Share Quantity')
                                    ->numeric()
                                    ->required(),
                            ]),
                        Grid::make(1)
                            ->schema([
                                TextInput::make('dividends')
                                    ->label('Dividends (Auto)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->readOnly(),
                            ]),
                    ]),
            ]);
    }
}
