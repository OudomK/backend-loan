<?php

namespace App\Filament\Resources\RepaymentTransactions\Schemas;

use App\Models\Loan;
use App\Models\Payment;
use App\Support\CurrencyHelper;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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
            ->description('Link the repayment to a loan and credit officer.')
                                    ->icon('heroicon-o-link')
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                        ])
                                            ->schema([
                                                Select::make('loan_id')
                                                    ->label('Loan')
                                                    ->relationship('loan', 'loan_code')
                                                    ->getOptionLabelFromRecordUsing(fn($record) => "Loan: {$record->loan_code} - {$record->borrower?->last_name} {$record->borrower?->first_name}")
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(fn($state, callable $set) => static::fillDueAmounts($state, $set))
                                                    ->required(),
                        Select::make('collector_id')
                            ->label('Credit Officer')
                            ->relationship('collector', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(),
                                            ]),
                                    ]),

                                Section::make('Metadata')
                                    ->icon('heroicon-o-information-circle')
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                            '2xl' => 3,
                                        ])
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
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol(static::loanCurrency($get('loan_id'))))
                                                    ->required()
                                                    ->placeholder('0.00')
                                                    ->helperText('Principal + interest only. Penalty is entered separately, and fee is auto-paid.')
                                                    ->columnSpan(12),
                                                TextInput::make('principal_paid')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol(static::loanCurrency($get('loan_id'))))
                                                    ->required()
                                                    ->placeholder('0.00')
                                                    ->live()
                                                    ->afterStateUpdated(fn($state, callable $set, callable $get) => static::syncTotalAmount($set, $get))
                                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                                TextInput::make('interest_paid')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol(static::loanCurrency($get('loan_id'))))
                                                    ->required()
                                                    ->placeholder('0.00')
                                                    ->live()
                                                    ->afterStateUpdated(fn($state, callable $set, callable $get) => static::syncTotalAmount($set, $get))
                                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                                TextInput::make('penalty_paid')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol(static::loanCurrency($get('loan_id'))))
                                                    ->default(0)
                                                    ->required()
                                                    ->placeholder('0.00')
                                                    ->live()
                                                    ->afterStateUpdated(fn($state, callable $set, callable $get) => static::syncTotalAmount($set, $get))
                                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                                TextInput::make('fee_paid')
                                                    ->label('Auto fee preview')
                                                    ->numeric()
                                                    ->prefix(fn (callable $get): string => CurrencyHelper::symbol(static::loanCurrency($get('loan_id'))))
                                                    ->default(0)
                                                    ->placeholder('0.00')
                                                    ->live()
                                                    ->afterStateUpdated(fn($state, callable $set, callable $get) => static::syncTotalAmount($set, $get))
                                                    ->columnSpan(['default' => 12, 'md' => 6]),
                                            ]),
                                    ]),
                            ])
                            ->columnSpan(['default' => 12, 'xl' => 7]),
                    ]),
            ]);
    }

    protected static function loanCurrency(mixed $loanId): string
    {
        if (blank($loanId)) {
            return CurrencyHelper::USD;
        }

        return CurrencyHelper::normalize(Loan::query()->whereKey($loanId)->value('currency'));
    }

    protected static function fillDueAmounts(mixed $loanId, callable $set): void
    {
        if (blank($loanId)) {
            static::setPaymentBreakdown($set, 0, 0, 0, 0);

            return;
        }

        $loan = Loan::query()->find($loanId);

        if (! $loan) {
            static::setPaymentBreakdown($set, 0, 0, 0, 0);

            return;
        }

        /** @var Payment|null $installment */
        $installment = $loan->payments()
            ->whereRaw('total_paid < (COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0) + COALESCE(fee_amount, 0) - 0.01)')
            ->orderBy('payment_date', 'asc')
            ->first();

        if (! $installment) {
            static::setPaymentBreakdown($set, 0, 0, 0, 0);

            return;
        }

        $feePaidSoFar = (float) ($installment->fee_paid ?? 0);
        $dueFee = max(0, (float) ($installment->fee_amount ?? 0) - $feePaidSoFar);

        $alreadyPaidToPrincipalAndInterest = max(0, (float) ($installment->total_paid ?? 0) - $feePaidSoFar);
        $interestAmount = (float) ($installment->interest_amount ?? 0);
        $interestPaidSoFar = min($interestAmount, $alreadyPaidToPrincipalAndInterest);
        $principalPaidSoFar = max(0, $alreadyPaidToPrincipalAndInterest - $interestPaidSoFar);

        $duePrincipal = max(0, (float) ($installment->principal_amount ?? 0) - $principalPaidSoFar);
        $dueInterest = max(0, $interestAmount - $interestPaidSoFar);

        static::setPaymentBreakdown($set, $duePrincipal, $dueInterest, 0, $dueFee);
    }

    protected static function setPaymentBreakdown(callable $set, float $principal, float $interest, float $penalty, float $fee): void
    {
        $set('principal_paid', static::formatMoney($principal));
        $set('interest_paid', static::formatMoney($interest));
        $set('penalty_paid', static::formatMoney($penalty));
        $set('fee_paid', static::formatMoney($fee));
        $set('amount_paid', static::formatMoney($principal + $interest));
    }

    protected static function syncTotalAmount(callable $set, callable $get): void
    {
        $principal = static::toFloat($get('principal_paid'));
        $interest = static::toFloat($get('interest_paid'));

        $set('amount_paid', static::formatMoney($principal + $interest));
    }

    protected static function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) $value;
    }

    protected static function formatMoney(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
