<?php

namespace App\Filament\Resources\PaymentQrs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PaymentQrsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Payment QR Codes')
            ->description('Manage payment QR codes for loan repayments, including active status and image paths.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('QR Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record) => $record->is_active ? 'Active for repayments' : 'Currently disabled'),
                ImageColumn::make('image_path')
                    ->label('QR Code')
                    ->disk('public')
                    ->square()
                    ->size(40),
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Edit QR code'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete QR code'),
                RestoreAction::make()
                    ->iconButton()
                    ->tooltip('Restore QR code'),
                ForceDeleteAction::make()
                    ->iconButton()
                    ->tooltip('Permanently delete'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Payment QR')
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
