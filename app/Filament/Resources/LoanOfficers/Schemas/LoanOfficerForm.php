<?php

namespace App\Filament\Resources\LoanOfficers\Schemas;

use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LoanOfficerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Loan Officer Profile')
                    ->description('Manage loan officer identity, employee link, lending authority, and status from one place.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                Select::make('employee_id')
                                    ->label('Link to Employee')
                                    ->relationship('employee', 'name', fn (Builder $query) => $query->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn ($record) => trim(($record->employee_code ? "{$record->employee_code} - " : '') . $record->name))
                                    ->searchable()
                                    ->preload()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Optional. Leave empty to create a standalone loan officer record.')
                                    ->live()
                                    ->native(false)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        $employee = $state ? Employee::find($state) : null;

                                        if (!$employee) {
                                            return;
                                        }

                                        if (blank($get('name'))) {
                                            $set('name', $employee->name);
                                        }

                                        if (blank($get('phone')) && filled($employee->phone)) {
                                            $set('phone', $employee->phone);
                                        }

                                        if (blank($get('gender')) && filled($employee->gender)) {
                                            $set('gender', $employee->gender);
                                        }

                                        if (blank($get('start_date')) && filled($employee->date_joined)) {
                                            $set('start_date', $employee->date_joined);
                                        }
                                    }),
                                TextInput::make('name')
                                    ->label('Officer Name')
                                    ->helperText('Required when no employee is linked.')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(30),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->native(false)
                                    ->required(),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])
                            ->schema([
                                Select::make('gender')
                                    ->label('Gender')
                                    ->options([
                                        'Male' => 'Male',
                                        'Female' => 'Female',
                                        'Other' => 'Other',
                                    ])
                                    ->native(false),
                                DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->native(false),
                                TextInput::make('max_loan_amount')
                                    ->label('Max Loan Amount')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ]),
            ]);
    }
}
