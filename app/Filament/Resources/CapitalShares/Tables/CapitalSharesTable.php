<?php

namespace App\Filament\Resources\CapitalShares\Tables;

use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CapitalSharesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Capital & Share')
            ->description('Monitor real capital investor accounts using the same structure as the frontend capital/share page.')
            ->defaultSort('borrowing_date', 'desc')
            ->columns([
                TextColumn::make('borrowing_date')
                    ->label('Date')
                    ->getStateUsing(fn ($record) => $record->borrowing_date ?? $record->created_at)
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse((string) $state)->format('d/m/Y') : '-')
                    ->sortable(),
                TextColumn::make('account_no')
                    ->label('Account Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('investor.customer_code')
                    ->label('Investor Code')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('owner_name')
                    ->label('Name')
                    ->getStateUsing(fn ($record) => $record->investor
                        ? trim("{$record->investor->last_name} {$record->investor->first_name}")
                        : '-')
                    ->searchable(['investor.first_name', 'investor.last_name'])
                    ->wrap()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->color('success'),
                TextColumn::make('currency')
                    ->toggleable(),
                TextColumn::make('share_qty')
                    ->label('Share Qty')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Invested Amount')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('dividends')
                    ->label('Dividends (Acc)')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->alignEnd(),
                TextColumn::make('total_dividend_paid')
                    ->label('Total Div. Paid')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->alignEnd(),
                TextColumn::make('last_dividend_date')
                    ->label('Last Div. Date')
                    ->date('d/m/Y')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Withdrawn' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Withdrawn' => 'Withdrawn',
                    ]),
                SelectFilter::make('currency')
                    ->options([
                        'USD' => 'USD',
                        'KHR' => 'KHR',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Share / Capital')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([]);
    }
}
