<?php

namespace App\Filament\Resources\Loans\Schemas;

use App\Support\CurrencyHelper;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class LoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    '2xl' => 2,
                ])
                    ->columnSpan('full')
                    ->schema([
                        Group::make()
                            ->schema([
                                Section::make('Loan Basics')
                                    ->description('Essential loan information.')
                                    ->icon('heroicon-o-banknotes')
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                            'xl' => 3,
                                        ])
                                            ->schema([
                                                Select::make('product_id')
                                                    ->relationship('product', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        if (blank($state)) return;
                                                        $product = \App\Models\LoanProduct::find($state);
                                                        if ($product) {
                                                            if ($product->interest_rate !== null) {
                                                                $set('interest_rate', $product->interest_rate);
                                                            }
                                                            if ($product->duration_months !== null) {
                                                                $set('duration_months', $product->duration_months);
                                                            }
                                                            if ($product->repayment_method !== null) {
                                                                $set('repayment_method', $product->repayment_method);
                                                            }
                                                            if ($product->fee_percentage !== null) {
                                                                $set('admin_fee', $product->fee_percentage);
                                                            }
                                                        }
                                                    }),
                                                TextInput::make('loan_code')
                                                    ->label('Loan Code')
                                                    ->required()
                                                    ->unique(ignoreRecord: true),
                                                Select::make('borrower_id')
                                                    ->relationship('borrower', 'id')
                                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->first_name} {$record->last_name} ({$record->customer_code})")
                                                    ->searchable(['first_name', 'last_name', 'customer_code', 'phone'])
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                        if (blank($state)) return;
                                                        $borrower = \App\Models\Borrower::find($state);
                                                        if ($borrower) {
                                                            $cycle = $get('loan_cycle') ?? 1;
                                                            $set('loan_code', "{$borrower->customer_code}-C{$cycle}");
                                                        }
                                                    }),
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
                                                    ->searchable(['name', 'phone'])
                                                    ->required(),
                                                TextInput::make('loan_cycle')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                        $borrowerId = $get('borrower_id');
                                                        if (blank($borrowerId)) return;
                                                        $borrower = \App\Models\Borrower::find($borrowerId);
                                                        if ($borrower) {
                                                            $set('loan_code', "{$borrower->customer_code}-C{$state}");
                                                        }
                                                    }),
                                            ]),
                                    ]),

                                Section::make('Participants')
                                    ->description('Co-borrowers and Guarantors.')
                                    ->icon('heroicon-o-user-group')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                        ])
                                            ->schema([
                                                Select::make('co_borrower_id')
                                                    ->relationship('coBorrower', 'id')
                                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->first_name} {$record->last_name} ({$record->customer_code})")
                                                    ->searchable(['first_name', 'last_name', 'customer_code', 'phone']),
                                                TextInput::make('co_borrower_relationship'),
                                                Select::make('guarantor_id')
                                                    ->relationship('guarantor', 'id')
                                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->first_name} {$record->last_name} ({$record->customer_code})")
                                                    ->searchable(['first_name', 'last_name', 'customer_code', 'phone']),
                                                TextInput::make('guarantor_relationship'),
                                            ]),
                                    ]),

                                Section::make('Refinancing')
                                    ->description('Details for refinanced loans.')
                                    ->icon('heroicon-o-arrow-path')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                            'xl' => 3,
                                        ])
                                            ->schema([
                                                Select::make('refinanced_from_loan_id')
                                                    ->relationship('refinancedFrom', 'loan_code')
                                                    ->searchable(),
                                                TextInput::make('refinance_fee')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->default(0),
                                                TextInput::make('refinanced_amount')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->default(0),
                                            ]),
                                    ]),
                            ])->columnSpan(1),

                        Group::make()
                            ->schema([
                                Section::make('Financial Terms')
                                    ->description('Amount, rates, and repayment schedule.')
                                    ->icon('heroicon-o-calculator')
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                            'xl' => 3,
                                        ])
                                            ->schema([
                                                TextInput::make('amount')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->required(),
                                                Select::make('currency')
                                                    ->options(CurrencyHelper::options())
                                                    ->default(CurrencyHelper::USD)
                                                    ->native(false)
                                                    ->live()
                                                    ->required(),
                                                TextInput::make('interest_rate')
                                                    ->label('Interest Rate (%)')
                                                    ->numeric()
                                                    ->suffix('%')
                                                    ->required(),
                                                TextInput::make('admin_fee')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->default(0),
                                            ]),
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                            'xl' => 3,
                                        ])
                                            ->schema([
                                                TextInput::make('duration_months')
                                                    ->label('Duration (Months)')
                                                    ->numeric()
                                                    ->required(),
                                                Select::make('payment_frequency')
                                                    ->options([
                                                        'monthly' => 'Monthly',
                                                        '15days' => 'Semi-monthly',
                                                        'term' => 'Term',
                                                    ])
                                                    ->default('monthly')
                                                    ->native(false)
                                                    ->required(),
                                                Select::make('repayment_method')
                                                    ->options([
                                                        'fixed_daily' => 'Fixed Daily (1x per day)',
                                                        'fixed_weekly' => 'Fixed Weekly (1x per week)',
                                                        'fixed_monthly' => 'Fixed Monthly (Flat)',
                                                        'linear_monthly' => 'Linear Monthly (Declining)',
                                                        'annuity_monthly' => 'Annuity Monthly',
                                                        'fixed_15days_70_30' => 'Fixed 15 Days (70/30)',
                                                        'fixed_15days_50_50' => 'Fixed 15 Days (50/50)',
                                                        'Balloon' => 'Balloon',
                                                        'negotiable' => 'Negotiable',
                                                    ])
                                                    ->searchable()
                                                    ->native(false)
                                                    ->required(),
                                            ]),
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                            'xl' => 3,
                                        ])
                                            ->schema([
                                                DatePicker::make('start_date')
                                                    ->required()
                                                    ->native(false),
                                                DatePicker::make('maturity_date')
                                                    ->native(false),
                                                TextInput::make('monthly_payment')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency'))),
                                                Select::make('payment_qr_id')
                                                    ->relationship('paymentQr', 'name')
                                                    ->label('Payment QR Code')
                                                    ->searchable()
                                                    ->preload(),
                                            ]),
                                    ]),

                                Section::make('Write-off & Recovery')
                                    ->description('Details for problematic loans.')
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                        ])
                                            ->schema([
                                                DatePicker::make('written_off_at')
                                                    ->native(false),
                                                TextInput::make('write_off_reason'),
                                                TextInput::make('write_off_balance')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->default(0),
                                                TextInput::make('recovery_amount')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol($get('currency')))
                                                    ->default(0),
                                                Select::make('disbursed_by_officer_id')
                                                    ->relationship('disburseOfficer', 'id')
                                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name}")
                                                    ->searchable(['name', 'phone']),
                                                TextInput::make('classify_wo')
                                                    ->label('WO Classification'),
                                            ]),
                                    ]),
                            ])->columnSpan(1),
                    ]),
            ]);
    }
}
