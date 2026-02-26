<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Details')
                    ->description('Account credentials and identity.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->label('Email Address')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('roles')
                                    ->relationship('roles', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                                TextInput::make('password')
                                    ->password()
                                    ->dehydrated(fn(?string $state): bool => filled($state))
                                    ->required(fn(string $operation): bool => $operation === 'create')
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }
}
