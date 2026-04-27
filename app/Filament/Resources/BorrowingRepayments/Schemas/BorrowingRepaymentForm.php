<?php

namespace App\Filament\Resources\BorrowingRepayments\Schemas;

use App\Models\Borrowing;
use App\Models\BorrowingSchedule;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BorrowingRepaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                \Filament\Schemas\Components\Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Core Payment')
                            ->description('Select account and capture principal, interest, and penalty details.')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        Select::make('borrowing_id')
                                            ->label('Borrowing Account')
                                            ->relationship('borrowing', 'account_no')
                                            ->getOptionLabelFromRecordUsing(fn(Borrowing $record): string => trim(
                                                (string) $record->account_no . ' (' . strtoupper((string) ($record->currency ?? 'USD')) . ')'
                                            ))
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->placeholder('Select borrowing account')
                                            ->required(),
                                        Select::make('schedule_id')
                                            ->label('Installment (Optional)')
                                            ->options(function (callable $get): array {
                                                $borrowingId = $get('borrowing_id');
                                                if (blank($borrowingId)) {
                                                    return [];
                                                }

                                                return BorrowingSchedule::query()
                                                    ->where('borrowing_id', $borrowingId)
                                                    ->orderBy('installment_no')
                                                    ->get()
                                                    ->mapWithKeys(fn(BorrowingSchedule $s) => [
                                                        $s->id => 'No. ' . $s->installment_no . ' • ' . ($s->due_date ?? '-'),
                                                    ])
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->placeholder('Optional'),
                                        DatePicker::make('payment_date')
                                            ->label('Payment Date')
                                            ->default(now())
                                            ->native(false)
                                            ->required(),
                                        TextInput::make('principal_paid')
                                            ->label('Principal Paid')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->prefix(fn(callable $get): string => static::currencyPrefix($get('borrowing_id')))
                                            ->placeholder('0.00')
                                            ->live()
                                            ->required(),
                                        TextInput::make('interest_paid')
                                            ->label('Interest Paid')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->prefix(fn(callable $get): string => static::currencyPrefix($get('borrowing_id')))
                                            ->placeholder('0.00')
                                            ->live()
                                            ->required(),
                                        TextInput::make('penalty_paid')
                                            ->label('Penalty Paid')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->prefix(fn(callable $get): string => static::currencyPrefix($get('borrowing_id')))
                                            ->placeholder('0.00')
                                            ->live(),
                                        Select::make('payment_method')
                                            ->options([
                                                'Cash' => 'Cash',
                                                'Bank Transfer' => 'Bank Transfer',
                                                'Cheque' => 'Cheque',
                                            ])
                                            ->default('Cash')
                                            ->native(false)
                                            ->placeholder('Select payment method')
                                            ->required(),
                                        Select::make('payment_status')
                                            ->options([
                                                'confirmed' => 'Confirmed',
                                                'pending' => 'Pending',
                                                'void' => 'Void',
                                            ])
                                            ->default('confirmed')
                                            ->native(false)
                                            ->placeholder('Select status')
                                            ->required(),
                                    ]),
                            ]),

                        Section::make('Reference & Notes')
                            ->description('Optional references and operator audit details.')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3])
                                    ->schema([
                                        TextInput::make('reference_no')
                                            ->label('Reference No')
                                            ->placeholder('Optional bank or transfer reference'),
                                        TextInput::make('receipt_no')
                                            ->label('Receipt No')
                                            ->helperText('Leave empty to auto-generate.')
                                            ->placeholder('Auto'),
                                        Select::make('received_by')
                                            ->relationship('receivedByUser', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->default(\Illuminate\Support\Facades\Auth::id())
                                            ->placeholder('Select user'),
                                    ]),
                                Textarea::make('remarks')
                                    ->rows(3)
                                    ->placeholder('Any internal note about this repayment...')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                \Filament\Schemas\Components\Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Section::make('Live Preview')
                            ->description('Preview calculated values.')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                TextEntry::make('preview_currency')
                                    ->label('Currency')
                                    ->extraAttributes(['class' => 'font-bold text-primary-600'])
                                    ->state(fn(callable $get): string => static::resolveCurrency($get('borrowing_id'))),
                                TextEntry::make('preview_total')
                                    ->label('Current Total')
                                    ->extraAttributes(['class' => 'text-xl font-black text-success-600'])
                                    ->state(function (callable $get): string {
                                        $total = static::toFloat($get('principal_paid'))
                                            + static::toFloat($get('interest_paid'))
                                            + static::toFloat($get('penalty_paid'));

                                        return static::formatAmount($total, static::resolveCurrency($get('borrowing_id')));
                                    }),
                                TextEntry::make('preview_receipt')
                                    ->label('Receipt Number')
                                    ->state(fn(callable $get): string => filled($get('receipt_no'))
                                        ? (string) $get('receipt_no')
                                        : 'Auto-generate on save'),
                                TextInput::make('total_paid')
                                    ->label('Total Paid (Saved)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Final value is calculated on save.'),
                                TextInput::make('balance_after_payment')
                                    ->label('Balance After (Saved)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),
            ]);
    }

    private static function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private static function resolveCurrency(mixed $borrowingId): string
    {
        if (blank($borrowingId)) {
            return 'USD';
        }

        $currency = Borrowing::query()->whereKey($borrowingId)->value('currency');

        return strtoupper((string) ($currency ?: 'USD'));
    }

    private static function currencyPrefix(mixed $borrowingId): string
    {
        return static::resolveCurrency($borrowingId) === 'KHR' ? 'KHR' : '$';
    }

    private static function formatAmount(float $value, string $currency): string
    {
        return strtoupper($currency) === 'KHR'
            ? 'KHR ' . number_format($value, 0)
            : '$' . number_format($value, 2);
    }
}
