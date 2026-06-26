<?php

namespace App\Filament\Resources\Dividends\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;

class DividendsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('declared_date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('payment_date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('currency')->badge(),
                TextColumn::make('distribution_basis')
                    ->badge(),
                TextColumn::make('total_shares_count')
                    ->formatStateUsing(fn ($state) => $state ? rtrim(rtrim(number_format((float) $state, 4, '.', ','), '0'), '.') : '0')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->numeric(2)
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                TextColumn::make('dividend_per_share')
                    ->formatStateUsing(fn ($state) => $state ? rtrim(rtrim(number_format((float) $state, 4, '.', ','), '0'), '.') . '%' : '0%')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'warning',
                        'Completed' => 'success',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('declared_date', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('distribute')
                    ->label('Distribute')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Distribute Dividend')
                    ->modalDescription('Are you sure you want to distribute this dividend to all eligible shareholders? This will create actual payment transactions.')
                    ->visible(fn ($record) => $record->status === 'Draft')
                    ->action(function ($record) {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                            $record->update(['status' => 'Completed']);
                            $paidAt = $record->payment_date
                                ? \Carbon\Carbon::parse($record->payment_date)->endOfDay()
                                : now();

                            $transactions = $record->transactions()->where('status', 'Pending')->get();

                            foreach ($transactions as $transaction) {
                                $transaction->update([
                                    'status' => 'Paid',
                                    'paid_at' => $paidAt,
                                    'payment_method' => 'Cash',
                                ]);

                                $share = \App\Models\CapitalShare::find($transaction->capital_share_id);
                                if ($share) {
                                    $share->increment('dividends', $transaction->amount);
                                    $share->increment('total_dividend_paid', $transaction->amount);
                                    $share->update(['last_dividend_date' => $paidAt->toDateString()]);

                                    \App\Models\CapitalShareTransaction::create([
                                        'capital_share_id' => $share->id,
                                        'transaction_type' => 'Dividend',
                                        'amount' => $transaction->amount,
                                        'share_qty' => $share->share_qty,
                                        'payment_method' => 'Cash',
                                        'transaction_date' => $paidAt,
                                        'description' => 'Dividend distribution from declaration #' . $record->id,
                                    ]);
                                }
                            }
                        });
                        \Filament\Notifications\Notification::make()
                            ->title('Dividend Distributed Successfully')
                            ->success()
                            ->send();
                    }),
                EditAction::make()->visible(fn ($record) => $record->status !== 'Completed'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
