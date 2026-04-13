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
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set) {
                                        $employee = \App\Models\Employee::find($state);
                                        if ($employee) {
                                            $set('salary', $employee->salary);
                                            // Trigger total calculation
                                            $set('total_payable', $employee->salary);
                                        }
                                    }),
                                DatePicker::make('month_year')
                                    ->label('Month/Year')
                                    ->format('Y-m-01')
                                    ->displayFormat('M Y')
                                    ->native(false)
                                    ->required()
                                    ->unique(modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule, $get) {
                                        return $rule->where('employee_id', $get('employee_id'));
                                    }, ignoreRecord: true)
                                    ->validationAttribute('Payroll for this month')
                                    ->helperText('Only one payroll record per employee per month.'),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextInput::make('salary')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($get, $set) => self::calculateTotal($get, $set)),
                                TextInput::make('allowance')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn ($get, $set) => self::calculateTotal($get, $set)),
                                TextInput::make('bonus')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn ($get, $set) => self::calculateTotal($get, $set)),
                                TextInput::make('deduction')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(fn ($get, $set) => self::calculateTotal($get, $set)),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('total_payable')
                                    ->numeric()
                                    ->prefix('$')
                                    ->readOnly()
                                    ->placeholder('Auto-calculated')
                                    ->extraInputAttributes(['class' => 'font-bold text-primary-600']),
                                Select::make('status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'paid' => 'Paid',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('pending')
                                    ->required(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('payment_date')
                                    ->native(false),
                                Select::make('payment_method')
                                    ->options([
                                        'Cash' => 'Cash',
                                        'Bank Transfer' => 'Bank Transfer',
                                        'Cheque' => 'Cheque',
                                        'Other' => 'Other',
                                    ])
                                    ->searchable(),
                            ]),
                    ]),
            ]);
    }

    protected static function calculateTotal($get, $set): void
    {
        $salary = (float) ($get('salary') ?? 0);
        $allowance = (float) ($get('allowance') ?? 0);
        $bonus = (float) ($get('bonus') ?? 0);
        $deduction = (float) ($get('deduction') ?? 0);

        $set('total_payable', $salary + $allowance + $bonus - $deduction);
    }
}
