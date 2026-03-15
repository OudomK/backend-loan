<?php

namespace App\Filament\Resources\Loans\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Loan Basics')
                    ->description('Essential loan information.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('loan_code')
                                    ->label('Loan Code')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                Select::make('borrower_id')
                                    ->relationship('borrower', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->last_name} {$record->first_name} ({$record->customer_code})")
                                    ->searchable()
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'active' => 'Active',
                                        'completed' => 'Completed',
                                        'paid_off' => 'Paid off',
                                    ])
                                    ->default('pending')
                                    ->required(),
                                Select::make('loan_officer_id')
                                    ->relationship('officer', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name}")
                                    ->searchable()
                                    ->required(),
                                TextInput::make('loan_cycle')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),
                            ]),
                    ]),

                Section::make('Financial Terms')
                    ->description('Amount, rates, and repayment schedule.')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                TextInput::make('currency')
                                    ->default('USD')
                                    ->required(),
                                TextInput::make('interest_rate')
                                    ->label('Interest Rate (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->required(),
                                TextInput::make('admin_fee')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('duration_months')
                                    ->label('Duration (Months)')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('payment_frequency')
                                    ->placeholder('Monthly, Weekly, etc.')
                                    ->required(),
                                TextInput::make('repayment_method')
                                    ->placeholder('Declining, Flat, etc.'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('start_date')
                                    ->required()
                                    ->native(false),
                                DatePicker::make('maturity_date')
                                    ->native(false),
                                TextInput::make('monthly_payment')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ]),

                Section::make('Participants')
                    ->description('Co-borrowers and Guarantors.')
                    ->icon('heroicon-o-user-group')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('co_borrower_id')
                                    ->relationship('coBorrower', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->last_name} {$record->first_name}")
                                    ->searchable(),
                                TextInput::make('co_borrower_relationship'),
                                Select::make('guarantor_id')
                                    ->relationship('guarantor', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->last_name} {$record->first_name}")
                                    ->searchable(),
                                TextInput::make('guarantor_relationship'),
                            ]),
                    ]),

                Section::make('Write-off & Recovery')
                    ->description('Details for problematic loans.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('written_off_at')
                                    ->native(false),
                                TextInput::make('write_off_reason'),
                                TextInput::make('write_off_balance')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('recovery_amount')
                                    ->numeric()
                                    ->default(0),
                                Select::make('disbursed_by_officer_id')
                                    ->relationship('disburseOfficer', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name}")
                                    ->searchable(),
                                TextInput::make('classify_wo')
                                    ->label('WO Classification'),
                            ]),
                    ]),

                Section::make('Refinancing')
                    ->description('Details for refinanced loans.')
                    ->icon('heroicon-o-arrow-path')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('refinanced_from_loan_id')
                                    ->relationship('refinancedFrom', 'loan_code')
                                    ->searchable(),
                                TextInput::make('refinance_fee')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                                TextInput::make('refinanced_amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                            ]),
                    ]),
            ]);
    }
}
