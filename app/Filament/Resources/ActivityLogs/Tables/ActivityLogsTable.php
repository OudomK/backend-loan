<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Audit Logs')
            ->description('Review who changed what across the system, along with the affected records and timestamps.')
            ->columns([
                TextColumn::make('log_name')
                    ->label('Channel')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('description')
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => filled($state) ? class_basename($state) : '-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Logged At')
                    ->dateTime('M j, Y g:i:s A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->bulkActions([
                // Logs are usually audit trails, deletion might not be desired but keeping it if they want.
                // For now, let's keep it clean.
            ]);
    }
}
