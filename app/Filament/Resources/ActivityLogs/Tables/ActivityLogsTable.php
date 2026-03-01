<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('description')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => class_basename($state))
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
                TextColumn::make('causer.roles.name')
                    ->label('Role')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                TextColumn::make('properties.ip')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable()
                    ->url(fn($state) => $state ? "https://ipinfo.io/{$state}" : null)
                    ->openUrlInNewTab(),
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
