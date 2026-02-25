<?php

namespace App\Filament\Resources\Payrolls\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payroll Entry')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('employee_id')
                                    ->relationship('employee', 'name')
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(
                                        fn($state, callable $set) =>
                                        $set('salary', \App\Models\Employee::find($state)?->salary)
                                    ),
                                TextInput::make('month_year')
                                    ->placeholder('e.g. 05-2024')
                                    ->required(),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextInput::make('salary')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                TextInput::make('allowance')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                                TextInput::make('bonus')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                                TextInput::make('deduction')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_payable')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->placeholder('Auto-calculated'),
                                DatePicker::make('payment_date')
                                    ->native(false),
                                Select::make('status')
                                    ->options([
                                        'Pending' => 'Pending',
                                        'Paid' => 'Paid',
                                    ])
                                    ->default('Pending')
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
