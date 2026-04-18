<?php

namespace App\Filament\Resources\CapitalShares\Schemas;

use App\Filament\Resources\CapitalShares\CapitalShareResource;
use App\Models\Investor;
use App\Support\CurrencyHelper;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
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

                Grid::make([
                    'default' => 1,
                    'lg' => 2,
                ])
                    ->columnSpan('full')
                    ->schema([
                        Group::make()
                            ->schema([
                                Section::make('Investment Details')
                                    ->description('Enter the investor and the capital amount being shared.')
                                    ->icon('heroicon-o-banknotes')
                                    ->schema([
                                        Select::make('investor_id')
                                            ->label('Investor (Name)')
                                            ->relationship('investor', 'first_name', fn (Builder $query) => $query->orderBy('last_name')->orderBy('first_name'))
                                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->last_name} {$record->first_name} ({$record->customer_code})")
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->helperText('Select the investor owning these shares.')
                                            ->afterStateUpdated(function ($state, callable $set): void {
                                                $investor = $state ? Investor::find($state) : null;
                                                $set('investor_code_preview', $investor?->customer_code);
                                            })
                                            ->required()
                                            ->columnSpanFull(),

                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('amount')
                                                    ->label('Capital Amount')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn (callable $set, callable $get) => static::syncParValue($set, $get)),
                                                Select::make('currency')
                                                    ->options(CurrencyHelper::options())
                                                    ->default(CurrencyHelper::USD)
                                                    ->required()
                                                    ->live()
                                                    ->native(false),
                                                TextInput::make('share_qty')
                                                    ->label('Share Quantity')
                                                    ->numeric()
                                                    ->suffix('units')
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn (callable $set, callable $get) => static::syncParValue($set, $get)),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('par_value')
                                                    ->label('Par Value (Auto)')
                                                    ->numeric()
                                                    ->step(0.00000001)
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->readOnly()
                                                    ->helperText('Calculated: Amount / Quantity'),
                                                TextInput::make('int_pay_mode')
                                                    ->label('Int Pay Mode')
                                                    ->placeholder('Monthly / Yearly')
                                                    ->maxLength(255),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                Select::make('status')
                                                    ->options([
                                                        'Active' => 'Active',
                                                        'Inactive' => 'Inactive',
                                                        'Closed' => 'Closed',
                                                    ])
                                                    ->default('Active')
                                                    ->required()
                                                    ->native(false),
                                                TextInput::make('balance')
                                                    ->label('Balance')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->default(0)
                                                    ->readOnly(),
                                            ]),

                                        Section::make('Dividends Status')
                                            ->description('Track historical and accumulated dividends.')
                                            ->compact()
                                            ->collapsible()
                                            ->schema([
                                                Grid::make(3)
                                                    ->schema([
                                                        TextInput::make('dividends')
                                                            ->label('Accumulated')
                                                            ->numeric()
                                                            ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                            ->default(0)
                                                            ->readOnly(),
                                                        TextInput::make('total_dividend_paid')
                                                            ->label('Total Paid')
                                                            ->numeric()
                                                            ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                            ->default(0),
                                                        DatePicker::make('last_dividend_date')
                                                            ->label('Last Paid Date')
                                                            ->native(false)
                                                            ->displayFormat('d/m/Y'),
                                                    ]),
                                            ]),
                                    ]),
                            ])->columnSpan(1),

                        Group::make()
                            ->schema([
                                Section::make('Account Identity')
                                    ->description('Official tracking and registration details.')
                                    ->icon('heroicon-o-identification')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('account_no')
                                                    ->label('Account Code')
                                                    ->default(fn () => CapitalShareResource::nextAccountNo())
                                                    ->required()
                                                    ->unique(ignoreRecord: true)
                                                    ->readOnly(),
                                                TextInput::make('certificate_no')
                                                    ->label('Certificate No')
                                                    ->placeholder('CERT-XXXX')
                                                    ->maxLength(255),
                                            ]),
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('contract_no')
                                                    ->label('Contract No')
                                                    ->placeholder('CS-CONT-XXXX')
                                                    ->maxLength(255),
                                                TextInput::make('transaction_no')
                                                    ->label('Transaction No')
                                                    ->placeholder('TXN-XXXX')
                                                    ->maxLength(255),
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
                                                TextInput::make('loan_account')
                                                    ->label('Loan Ref Account')
                                                    ->placeholder('L-ACC-XXXX')
                                                    ->maxLength(255),
                                            ]),
                                        Grid::make(2)
                                            ->schema([
                                                DatePicker::make('borrowing_date')
                                                    ->label('Registration Date')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->default(fn () => Carbon::today()->toDateString())
                                                    ->required(),
                                                DatePicker::make('purchase_date')
                                                    ->label('Purchase Date')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->default(fn () => Carbon::today()->toDateString()),
                                            ]),
                                    ]),

                                Section::make('Relationships & Legacy')
                                    ->description('Additional mapping from the legacy system (Optional).')
                                    ->icon('heroicon-o-link')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('lender_id')
                                                    ->label('Legacy Lender')
                                                    ->relationship('lender', 'name')
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('borrower_id')
                                                    ->label('Related Borrower')
                                                    ->relationship('borrower', 'first_name')
                                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->last_name} {$record->first_name}")
                                                    ->searchable()
                                                    ->preload(),
                                            ]),
                                        TextInput::make('holder_id')
                                            ->label('Old Holder Reference')
                                            ->placeholder('HOLDER-XXXX'),
                                    ]),
                            ])->columnSpan(1),
                    ]),
            ]);
    }

    protected static function syncParValue(callable $set, callable $get): void
    {
        $amount = (float) ($get('amount') ?: 0);
        $qty = (int) ($get('share_qty') ?: 0);

        if ($qty > 0) {
            $set('par_value', number_format($amount / $qty, 8, '.', ''));
        } else {
            $set('par_value', 0);
        }
    }
}
