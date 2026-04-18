<?php

namespace App\Filament\Resources\SavingAccounts\Schemas;

use App\Models\Lender;
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

class SavingAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('category')
                    ->default('Loan Capital'),

                Grid::make([
                    'default' => 1,
                    'lg' => 2,
                ])
                    ->columnSpan('full')
                    ->schema([
                        Group::make()
                            ->schema([
                                Section::make('Main Borrowing Details')
                                    ->description('Enter the lender and financial terms of the borrowing.')
                                    ->icon('heroicon-o-banknotes')
                                    ->schema([
                                        Select::make('lender_id')
                                            ->label('Lender')
                                            ->relationship('lender', 'name', fn (Builder $query) => $query->orderBy('name'))
                                            ->getOptionLabelFromRecordUsing(fn ($record) => trim(($record->lender_code ? "{$record->lender_code} - " : '') . $record->name))
                                            ->searchable(['name', 'lender_code'])
                                            ->preload()
                                            ->default(fn () => Lender::query()->orderBy('name')->value('id'))
                                            ->required()
                                            ->columnSpanFull(),

                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('amount')
                                                    ->label('Amount')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->required(),
                                                Select::make('currency')
                                                    ->options(CurrencyHelper::options())
                                                    ->default(CurrencyHelper::USD)
                                                    ->required()
                                                    ->live()
                                                    ->native(false),
                                                TextInput::make('term_months')
                                                    ->label('Term (Months)')
                                                    ->numeric()
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                        self::syncMaturityDate($set, $get);
                                                    }),
                                            ]),

                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('interest_rate')
                                                    ->label('Interest Rate (%)')
                                                    ->numeric()
                                                    ->suffix('%')
                                                    ->required(),
                                                TextInput::make('fee')
                                                    ->label('Fee')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->default(0),
                                                Select::make('payment_method')
                                                    ->label('Payment Method')
                                                    ->options([
                                                        'Balloon' => 'Balloon',
                                                        'Declining' => 'Declining',
                                                        'Negotiable' => 'Negotiable',
                                                    ])
                                                    ->default('Balloon')
                                                    ->required()
                                                    ->native(false),
                                            ]),
                                    ]),

                                Section::make('Operational Info')
                                    ->description('Additional terms and payment modes.')
                                    ->icon('heroicon-o-cog')
                                    ->collapsible()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('int_pay_mode')
                                                    ->label('Int Pay Mode')
                                                    ->placeholder('Monthly / At Maturity')
                                                    ->maxLength(255),
                                                TextInput::make('sl_term')
                                                    ->label('S/L Term')
                                                    ->default('Short Term')
                                                    ->maxLength(255),
                                            ]),
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('late_principal')
                                                    ->label('Late Principal Penalty')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->default(0),
                                                TextInput::make('loan_interest')
                                                    ->label('Late Interest Penalty')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->default(0),
                                            ]),
                                    ]),
                            ])->columnSpan(1),

                        Group::make()
                            ->schema([
                                Section::make('Identity & Reference')
                                    ->description('Official tracking and contract numbers.')
                                    ->icon('heroicon-o-identification')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('account_no')
                                                    ->label('Account No')
                                                    ->required()
                                                    ->maxLength(255),
                                                TextInput::make('contract_no')
                                                    ->label('Contract No')
                                                    ->maxLength(255),
                                            ]),
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('transaction_no')
                                                    ->label('Transaction No')
                                                    ->maxLength(255),
                                                TextInput::make('loan_account')
                                                    ->label('Loan Account')
                                                    ->maxLength(255),
                                            ]),
                                    ]),

                                Section::make('Schedule Dates')
                                    ->description('Key dates for the borrowing period.')
                                    ->icon('heroicon-o-calendar-days')
                                    ->schema([
                                        DatePicker::make('borrowing_date')
                                            ->label('Borrowing Date')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(fn () => Carbon::today()->toDateString())
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                self::syncFirstPayDate($state, $set);
                                                self::syncMaturityDate($set, $get);
                                            }),
                                        Grid::make(2)
                                            ->schema([
                                                DatePicker::make('first_pay_date')
                                                    ->label('1st Pay Date')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->default(fn () => Carbon::today()->addMonth()->toDateString()),
                                                DatePicker::make('maturity_date')
                                                    ->label('Maturity Date (Auto)')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ]),
                                    ]),
                            ])->columnSpan(1),
                    ]),
            ]);
    }

    private static function syncFirstPayDate(mixed $borrowingDate, callable $set): void
    {
        $parsedDate = self::parseDate($borrowingDate);

        if (!$parsedDate) {
            $set('first_pay_date', null);

            return;
        }

        $set('first_pay_date', $parsedDate->copy()->addMonth()->toDateString());
    }

    private static function syncMaturityDate(callable $set, callable $get): void
    {
        $borrowingDate = self::parseDate($get('borrowing_date'));
        $termMonths = (int) ($get('term_months') ?: 0);

        if (!$borrowingDate || $termMonths <= 0) {
            $set('maturity_date', null);

            return;
        }

        $set('maturity_date', $borrowingDate->copy()->addMonthsNoOverflow($termMonths)->toDateString());
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
