<?php

namespace App\Filament\Resources\RepaymentTransactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RepaymentTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->columnSpan('full')
                    ->schema([
                        Group::make()
                            ->schema([
                                Section::make('Transaction Context')
                                    ->description('Link the repayment to a loan and collector.')
                                    ->icon('heroicon-o-link')
                                    ->schema([
                                        Grid::make(1)
                                            ->schema([
                                                Select::make('loan_id')
                                                    ->label('Loan')
                                                    ->relationship('loan', 'loan_code')
                                                    ->getOptionLabelFromRecordUsing(fn($record) => "Loan: {$record->loan_code} - {$record->borrower?->last_name} {$record->borrower?->first_name}")
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(),
                                                Select::make('collector_id')
                                                    ->label('Collector')
                                                    ->relationship('collector', 'name')
                                                    ->searchable()
                                                    ->preload(),
                                            ]),
                                    ]),

                                Section::make('Metadata')
                                    ->icon('heroicon-o-information-circle')
                                    ->schema([
                                        Grid::make(1)
                                            ->schema([
                                                Select::make('payment_method')
                                                    ->options([
                                                        'Cash' => 'Cash',
                                                        'Bank Transfer' => 'Bank Transfer',
                                                        'Mobile Money' => 'Mobile Money',
                                                        'Cheque' => 'Cheque',
                                                        'Internal' => 'Internal',
                                                    ])
                                                    ->default('Cash')
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('repayment_type')
                                                    ->options([
                                                        'Normal' => 'Normal',
                                                        'Prepayment' => 'Prepayment',
                                                        'Partial' => 'Partial',
                                                        'Pay Off' => 'Pay Off',
                                                        'Recovery' => 'Recovery',
                                                        'Withdraw' => 'Withdraw',
                                                        'Refinance' => 'Refinance',
                                                        'Reschedule' => 'Reschedule',
                                                    ])
                                                    ->default('Normal')
                                                    ->native(false)
                                                    ->required(),
                                                DatePicker::make('transaction_date')
                                                    ->default(now())
                                                    ->required()
                                                    ->native(false),
                                            ]),
                                    ]),
                            ])
                            ->columnSpan(['default' => 12, 'xl' => 5]),

                        Group::make()
                            ->schema([
                                Section::make('Payment Details')
                                    ->description('Breakdown of the payment.')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->schema([
                                        Grid::make(12)
                                            ->schema([
                                                TextInput::make('amount_paid')
                                                    ->label('Total Amount Paid')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->required()
                                                    ->placeholder('0.00')
                                                    ->helperText('Total should match principal + interest + penalty + fee.')
                                                    ->columnSpan(12),
                                                TextInput::make('principal_paid')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->required()
                                                    ->placeholder('0.00')
                                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                                TextInput::make('interest_paid')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->required()
                                                    ->placeholder('0.00')
                                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                                TextInput::make('penalty_paid')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->default(0)
                                                    ->required()
                                                    ->placeholder('0.00')
                                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                                TextInput::make('fee_paid')
                                                    ->label('Fee paid')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->default(0)
                                                    ->placeholder('0.00')
                                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                            ]),
                                    ]),
                            ])
                            ->columnSpan(['default' => 12, 'xl' => 7]),
                    ]),
            ]);
    }
}
