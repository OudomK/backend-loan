<?php

namespace App\Filament\Resources\Positions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Position Details')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('code')
                                    ->maxLength(50)
                                    ->default(function () {
                                        $latest = \App\Models\Position::orderBy('id', 'desc')->first();
                                        $nextId = $latest ? $latest->id + 1 : 1;
                                        return 'POS-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
                                    }),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('department')
                                    ->maxLength(100),
                                TextInput::make('type')
                                    ->placeholder('e.g. Full-time'),
                                TextInput::make('base_salary')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                        Select::make('status')
                            ->options([
                                'Active' => 'Active',
                                'Inactive' => 'Inactive',
                            ])
                            ->default('Active')
                            ->required(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        Textarea::make('requirements')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
