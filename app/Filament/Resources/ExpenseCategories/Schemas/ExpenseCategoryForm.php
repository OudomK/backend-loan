<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expense Category')
                    ->icon('heroicon-o-tag')
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
                                    'Administrative Expenses' => 'Administrative Expenses',
                                    'Operating Expenses' => 'Operating Expenses',
                                    'Selling & Marketing Expenses' => 'Selling & Marketing Expenses',
                                    'Finance Costs' => 'Finance Costs',
                                    'Other Operating Expenses' => 'Other Operating Expenses',
                                    'Miscellaneous Expense' => 'Miscellaneous Expense',
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
