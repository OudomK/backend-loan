<?php

namespace App\Filament\Resources\RepaymentTransactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
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
                Section::make('Transaction Context')
                    ->description('Link the repayment to a loan and collector.')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('loan_id')
                                    ->relationship('loan', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "Loan: {$record->loan_code} - {$record->borrower?->first_name} {$record->borrower?->last_name}")
                                    ->searchable()
                                    ->required(),
                                Select::make('collector_id')
                                    ->relationship('collector', 'name')
                                    ->searchable(),
                            ]),
                    ]),

                Section::make('Payment Details')
                    ->description('Breakdown of the payment.')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount_paid')
                                    ->label('Total Amount Paid')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('principal_paid')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                TextInput::make('interest_paid')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                TextInput::make('penalty_paid')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->required(),
                            ]),
                    ]),

                Section::make('Metadata')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('payment_method')
                                    ->required(),
                                TextInput::make('repayment_type')
                                    ->default('Normal')
                                    ->required(),
                                DatePicker::make('transaction_date')
                                    ->default(now())
                                    ->required()
                                    ->native(false),
                            ]),
                    ]),
            ]);
    }
}
