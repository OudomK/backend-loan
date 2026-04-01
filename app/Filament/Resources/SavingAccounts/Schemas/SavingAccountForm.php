<?php

namespace App\Filament\Resources\SavingAccounts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SavingAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                                        'Goal Saving' => 'Mid term',
                                        'Fixed Deposit' => 'Long term',
                                    ])
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Dormant' => 'Dormant',
                                        'Closed' => 'Closed',
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
                                Select::make('lender_id')
                                    ->relationship('lender', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name}")
                                    ->searchable(),
                                TextInput::make('loan_account')
                                    ->label('Loan Account'),
                                TextInput::make('account_no')
                                    ->label('Account No (Secondary)'),
                                TextInput::make('currency')
                                    ->default('USD')
                                    ->required(),
                            ]),
                    ]),

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

                Section::make('Borrowing Terms & Balances')
                    ->description('Outstanding balance, interest, and repayment terms.')
                    ->icon('heroicon-o-calculator')
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
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_deposits')
                                    ->numeric()
                                    ->prefix('$'),
                                TextInput::make('total_withdrawals')
                                    ->numeric()
                                    ->prefix('$'),
                                TextInput::make('interest_earned')
                                    ->numeric()
                                    ->prefix('$'),
                                TextInput::make('payment_method'),
                                DatePicker::make('first_pay_date')
                                    ->native(false),
                                TextInput::make('int_pay_mode')
                                    ->label('Interest Pay Mode'),
                                TextInput::make('fee')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                                TextInput::make('term'),
                                TextInput::make('sl_term')
                                    ->label('SL Term'),
                                TextInput::make('late_principal')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                                TextInput::make('loan_interest')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                            ]),
                    ]),
            ]);
    }
}
