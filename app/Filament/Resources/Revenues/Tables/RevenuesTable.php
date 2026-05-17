<?php

namespace App\Filament\Resources\Revenues\Tables;

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

class RevenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('General Revenues')
            ->description('Manage and track all non-interest income and other revenues.')
            ->defaultSort('transaction_date', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('reference_no')
                    ->label('Ref#')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('revenue_category.name')
                    ->label('Category')
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable()
                    ->alignRight()
                    ->weight('bold'),
                TextColumn::make('payment_method')
                    ->label('Method')
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('revenue_category_id')
                    ->label('Category')
                    ->relationship('revenue_category', 'name'),
                SelectFilter::make('currency')
                    ->options([
                        'USD' => 'USD',
                        'KHR' => 'KHR',
                    ]),
                TrashedFilter::make(),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('New revenue')
                    ->url(fn (): string => \App\Filament\Resources\Revenues\RevenueResource::getUrl('create')),
            ])
            ->actions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
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
