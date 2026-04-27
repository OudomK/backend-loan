<?php

namespace App\Filament\Resources\CapitalShares\Tables;

use App\Support\CurrencyHelper;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CapitalSharesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Capital & Share')
            ->description('Monitor real capital investor accounts using the same structure as the frontend capital/share page.')
            ->defaultSort('borrowing_date', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('borrowing_date')
                    ->label('Date')
                    ->getStateUsing(fn ($record) => $record->borrowing_date ?? $record->created_at)
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse((string) $state)->format('d/m/Y') : '-')
                    ->sortable()
                    ->description(fn ($record): ?string => filled($record->account_no) ? $record->account_no : null),
                TextColumn::make('account_no')
                    ->label('Account Code')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('investor.customer_code')
                    ->label('Investor Code')
                    ->searchable()
                    ->visibleFrom('2xl'),
                TextColumn::make('owner_name')
                    ->label('Name')
                    ->getStateUsing(fn($record) => $record->investor
                        ? trim("{$record->investor->last_name} {$record->investor->first_name}")
                        : '-')
                    ->searchable(['investor.first_name', 'investor.last_name'])
                    ->wrap()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->investor?->customer_code) ? $record->investor->customer_code : null,
                        filled($record->currency) ? CurrencyHelper::normalize($record->currency) : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('category')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currency')
                    ->formatStateUsing(fn ($state): string => CurrencyHelper::normalize($state))
                    ->visibleFrom('2xl'),
                TextColumn::make('share_qty')
                    ->label('Share Qty')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('amount')
                    ->label('Invested Amount')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->balance) ? 'Balance ' . CurrencyHelper::format($record->balance, $record->currency, true, 2) : null,
                        filled($record->share_qty) ? 'Qty ' . number_format((float) $record->share_qty, 0) : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('dividends')
                    ->label('Dividends (Acc)')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_dividend_paid')
                    ->label('Total Div. Paid')
                    ->formatStateUsing(fn ($state, $record): string => CurrencyHelper::format($state, $record->currency, true, 2))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_dividend_date')
                    ->label('Last Div. Date')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Active' => 'success',
                        'Withdrawn' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Withdrawn' => 'Withdrawn',
                    ]),
                SelectFilter::make('currency')
                    ->options(CurrencyHelper::options()),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage capital share'),
                DeleteAction::make()->iconButton(),
                RestoreAction::make()->iconButton(),
                ForceDeleteAction::make()->iconButton(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Share / Capital')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
