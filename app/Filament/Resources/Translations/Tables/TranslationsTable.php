<?php

namespace App\Filament\Resources\Translations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TranslationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Translations')
            ->description('Manage system translations and localizations.')
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('New translation')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->columns([
                TextColumn::make('key')
                    ->label('Translation Key')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-key')
                    ->copyable()
                    ->copyMessage('Key copied to clipboard')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('en')
                    ->label('English (EN)')
                    ->searchable()
                    ->wrap()
                    ->icon('heroicon-o-language')
                    ->color('info'),
                TextColumn::make('kh')
                    ->label('Khmer (KH)')
                    ->searchable()
                    ->wrap()
                    ->icon('heroicon-o-language')
                    ->color('success'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->button()
                    ->outlined()
                    ->icon('heroicon-o-pencil-square'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->deferLoading();
    }
}
