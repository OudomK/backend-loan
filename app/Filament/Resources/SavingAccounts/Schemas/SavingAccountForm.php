<?php

namespace App\Filament\Resources\SavingAccounts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Split;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SavingAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Row 1: Borrowing Overview (full width)
                Section::make('Borrowing Overview')
                    ->description('Primary borrowing identification.')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('account_number')
                                    ->label('Borrowing Ref No')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                Select::make('account_type')
                                    ->label('Borrowing Plan')
                                    ->options([
                                        'Daily Saving' => 'Short term',
                                        'Goal Saving'  => 'Mid term',
                                        'Fixed Deposit' => 'Long term',
                                    ])
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'Active'  => 'Active',
                                        'Dormant' => 'Dormant',
                                        'Closed'  => 'Closed',
                                    ])
                                    ->default('Active')
                                    ->required(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Select::make('category')
                                    ->options([
                                        'Real Capital' => 'Real capital',
                                        'Loan Capital' => 'Loan capital',
                                    ])
                                    ->default('Loan Capital')
                                    ->required(),
                                TextInput::make('transaction_no')
                                    ->label('Transaction No'),
                                TextInput::make('contract_no')
                                    ->label('Contract No'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Select::make('lender_id')
                                    ->label('Lender')
                                    ->relationship('lender', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name}")
                                    ->searchable(),
                                TextInput::make('loan_account')
                                    ->label('Loan Account'),
                                TextInput::make('account_no')
                                    ->label('Account No (Secondary)'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('currency')
                                    ->default('USD')
                                    ->required()
                                    ->columnSpan(1),
                            ]),
                    ]),

                // Row 2: Related Parties (full width)
                Section::make('Related Parties')
                    ->description('Link this borrowing to related customers.')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('saver_id')
                                    ->label('Client')
                                    ->relationship('saver', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->last_name} {$record->first_name}")
                                    ->searchable(),
                                Select::make('borrower_id')
                                    ->label('Borrower')
                                    ->relationship('borrower', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->last_name} {$record->first_name}")
                                    ->searchable(),
                            ]),
                    ]),

                // Row 3: Core Financials
                Section::make('Core Financials')
                    ->description('Principal amount, balance, and interest terms.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                TextInput::make('balance')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                TextInput::make('interest_rate')
                                    ->label('Interest Rate (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->required(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('term_months')
                                    ->label('Term (Months)')
                                    ->numeric()
                                    ->required(),
                                DatePicker::make('borrowing_date')
                                    ->native(false),
                                DatePicker::make('maturity_date')
                                    ->native(false),
                            ]),
                    ]),

                // Row 4: Deposits & Withdrawals
                Section::make('Deposits & Withdrawals')
                    ->description('Track total deposits, withdrawals, and earned interest.')
                    ->icon('heroicon-o-arrows-right-left')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_deposits')
                                    ->label('Total Deposits')
                                    ->numeric()
                                    ->prefix('$'),
                                TextInput::make('total_withdrawals')
                                    ->label('Total Withdrawals')
                                    ->numeric()
                                    ->prefix('$'),
                                TextInput::make('interest_earned')
                                    ->label('Interest Earned')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ]),

                // Row 5: Payment Settings
                Section::make('Payment Settings')
                    ->description('Configure payment method, schedule, and fees.')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('payment_method')
                                    ->label('Payment Method'),
                                DatePicker::make('first_pay_date')
                                    ->label('First Pay Date')
                                    ->native(false),
                                TextInput::make('int_pay_mode')
                                    ->label('Interest Pay Mode'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('fee')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                                TextInput::make('term')
                                    ->label('Term'),
                                TextInput::make('sl_term')
                                    ->label('SL Term'),
                            ]),
                    ]),

                // Row 6: Late Fees & Loan Interest
                Section::make('Late Fees & Loan Interest')
                    ->description('Outstanding penalties and accrued loan interest.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('late_principal')
                                    ->label('Late Principal')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                                TextInput::make('loan_interest')
                                    ->label('Loan Interest')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                            ]),
                    ]),
            ]);
    }
}
