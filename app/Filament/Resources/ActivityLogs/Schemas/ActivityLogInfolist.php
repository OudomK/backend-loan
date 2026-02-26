<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->schema([
                        TextEntry::make('description'),
                        TextEntry::make('log_name'),
                        TextEntry::make('created_at')
                            ->label('Logged At')
                            ->dateTime(),
                        TextEntry::make('properties.ip')
                            ->label('IP Address')
                            ->url(fn($state) => $state ? "https://ipinfo.io/{$state}" : null)
                            ->openUrlInNewTab()
                            ->color('primary'),
                    ])->columns(4),

                Section::make('Subject & Causer')
                    ->schema([
                        TextEntry::make('subject_type')
                            ->label('Subject Model')
                            ->formatStateUsing(fn($state) => $state ? class_basename($state) : '-'),
                        TextEntry::make('subject_id')
                            ->label('Subject ID'),
                        TextEntry::make('causer.name')
                            ->label('User Name'),
                        TextEntry::make('causer.roles.name')
                            ->label('User Role')
                            ->badge()
                            ->color('info'),
                    ])->columns(2),

                Section::make('Properties')
                    ->description('JSON representation of changed attributes')
                    ->schema([
                        TextEntry::make('properties')
                            ->label('')
                            ->formatStateUsing(fn($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->extraAttributes(['class' => 'font-mono text-xs']),
                    ]),
            ]);
    }
}
