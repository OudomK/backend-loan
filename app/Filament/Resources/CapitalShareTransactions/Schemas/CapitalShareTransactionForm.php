<?php

namespace App\Filament\Resources\CapitalShareTransactions\Schemas;

use App\Filament\Resources\CapitalShareTransactions\CapitalShareTransactionResource;
use App\Models\CapitalShare;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CapitalShareTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                \Filament\Schemas\Components\Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Transaction Core')
                            ->description('Choose account, transaction type, and financial details.')
                            ->icon('heroicon-o-arrows-right-left')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        Select::make('capital_share_id')
                                            ->label('Capital Share Account')
                                            ->relationship('capitalShare', 'account_no')
                                            ->getOptionLabelFromRecordUsing(fn(CapitalShare $record): string => trim(
                                                (string) $record->account_no . ' (' . strtoupper((string) ($record->currency ?? 'USD')) . ')'
                                            ))
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->placeholder('Select capital/share account')
                                            ->required(),
                                        Select::make('transaction_type')
                                            ->options(function (callable $get): array {
                                                $shareId = $get('capital_share_id');
                                                if (blank($shareId)) {
                                                    return ['Initial' => 'Initial'];
                                                }

                                                $category = CapitalShare::query()
                                                    ->whereKey($shareId)
                                                    ->value('category');

                                                return CapitalShareTransactionResource::allowedTransactionTypesForCategory(
                                                    (string) ($category ?? '')
                                                );
                                            })
                                            ->native(false)
                                            ->live()
                                            ->placeholder('Select transaction type')
                                            ->required(),
                                        TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix(fn(callable $get): string => static::currencyPrefix($get('capital_share_id')))
                                            ->placeholder('0.00')
                                            ->live()
                                            ->required(),
                                        TextInput::make('share_qty')
                                            ->label('Share Qty')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->placeholder('0')
                                            ->live(),
                                        Select::make('payment_method')
                                            ->options(\App\Models\PaymentMethod::where('is_active', true)->pluck('name', 'name')->toArray())
                                            ->placeholder('Optional')
                                            ->native(false),
                                        DateTimePicker::make('transaction_date')
                                            ->label('Transaction Date')
                                            ->default(now())
                                            ->native(false)
                                            ->required(),
                                    ]),
                            ]),

                        Section::make('Reference & Audit')
                            ->description('Reference numbers, operator, and internal notes.')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])
                                    ->schema([
                                        TextInput::make('reference_no')
                                            ->label('Reference No')
                                            ->placeholder('Optional reference'),
                                        Select::make('performed_by')
                                            ->label('Performed By')
                                            ->relationship('performedByUser', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->default(\Illuminate\Support\Facades\Auth::id())
                                            ->placeholder('Select user'),
                                    ]),
                                Textarea::make('description')
                                    ->rows(3)
                                    ->placeholder('Describe why this transaction was recorded...')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                \Filament\Schemas\Components\Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Section::make('Live Preview')
                            ->description('Preview calculated values.')
                            ->icon('heroicon-o-presentation-chart-line')
                            ->schema([
                                TextEntry::make('preview_currency')
                                    ->label('Currency')
                                    ->extraAttributes(['class' => 'font-bold text-primary-600'])
                                    ->state(fn(callable $get): string => static::resolveCurrency($get('capital_share_id'))),
                                TextEntry::make('preview_amount')
                                    ->label('Amount Preview')
                                    ->extraAttributes(['class' => 'text-xl font-black text-success-600'])
                                    ->state(function (callable $get): string {
                                        return static::formatAmount(
                                            static::toFloat($get('amount')),
                                            static::resolveCurrency($get('capital_share_id'))
                                        );
                                    }),
                                TextEntry::make('preview_unit')
                                    ->label('Avg Amount / Share')
                                    ->state(function (callable $get): string {
                                        $qty = (int) static::toFloat($get('share_qty'));
                                        if ($qty <= 0) {
                                            return '-';
                                        }

                                        $unit = static::toFloat($get('amount')) / $qty;

                                        return static::formatAmount(
                                            $unit,
                                            static::resolveCurrency($get('capital_share_id'))
                                        );
                                    }),
                                TextEntry::make('preview_type_hint')
                                    ->label('Transaction Hint')
                                    ->state(function (callable $get): string {
                                        return match ((string) ($get('transaction_type') ?? '')) {
                                            'Deposit' => 'Increases invested capital.',
                                            'Withdrawal' => 'Reduces invested capital.',
                                            'Dividend' => 'Distribution from declared dividend.',
                                            'Repayment' => 'Capital repayment movement.',
                                            'Initial' => 'Opening transaction for this account.',
                                            default => 'Select transaction type.',
                                        };
                                    }),
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

    private static function resolveCurrency(mixed $shareId): string
    {
        if (blank($shareId)) {
            return 'USD';
        }

        $currency = CapitalShare::query()->whereKey($shareId)->value('currency');

        return strtoupper((string) ($currency ?: 'USD'));
    }

    private static function currencyPrefix(mixed $shareId): string
    {
        return static::resolveCurrency($shareId) === 'KHR' ? 'KHR' : '$';
    }

    private static function formatAmount(float $value, string $currency): string
    {
        return strtoupper($currency) === 'KHR'
            ? 'KHR ' . number_format($value, 0)
            : '$' . number_format($value, 2);
    }
}
