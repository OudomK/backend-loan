<?php

namespace App\Filament\Resources\RevenueCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RevenueCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Revenue Category')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Select::make('group_name')
                                ->label('Group')
                                ->native(false)
                                ->options([
                                    'Revenue' => 'Revenue',
                                    'Operating Income' => 'Operating Income',
                                    'Non-Operating Income' => 'Non-Operating Income',
                                    'Other Revenue' => 'Other Revenue',
                                ])
                                ->searchable(),
                            TextInput::make('sort_order')
                                ->numeric()
                                ->default(0)
                                ->required(),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
