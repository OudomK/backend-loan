<?php

namespace App\Filament\Resources\Expenses\Tables;

use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use App\Support\CurrencyHelper;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Expenses')
            ->description('Manage and track all company expenses.')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('expenseCategory.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record) => CurrencyHelper::display($state, $record->currency ?? CurrencyHelper::USD))
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('currency')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('reference_no')
                    ->label('Ref No')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                \Filament\Tables\Filters\SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('expenseCategory', 'name'),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('New expense')
                    ->url(fn (): string => \App\Filament\Resources\Expenses\ExpenseResource::getUrl('create')),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
