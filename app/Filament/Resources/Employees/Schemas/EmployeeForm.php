<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('name_kh')
                                    ->label('Name (KH)')
                                    ->maxLength(255),
                                TextInput::make('employee_code')
                                    ->label('Code')
                                    ->disabled()
                                    ->placeholder('Auto-generated'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Select::make('gender')
                                    ->options([
                                        'Male' => 'Male',
                                        'Female' => 'Female',
                                        'Other' => 'Other',
                                    ]),
                                DatePicker::make('dob')
                                    ->label('Date of Birth')
                                    ->native(false),
                                Select::make('status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Inactive' => 'Inactive',
                                        'Resigned' => 'Resigned',
                                    ])
                                    ->default('Active')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Employment Details')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('position_id')
                                    ->relationship('position', 'name')
                                    ->searchable()
                                    ->required(),
                                TextInput::make('employment_type')
                                    ->placeholder('e.g. Full-time, Contract'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('salary')
                                    ->numeric()
                                    ->prefix('$'),
                                DatePicker::make('date_joined')
                                    ->native(false),
                                DatePicker::make('contract_end_date')
                                    ->native(false),
                            ]),
                    ]),

                Section::make('Contact & Banking')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('phone')
                                    ->tel(),
                                TextInput::make('email')
                                    ->email(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('bank_name'),
                                TextInput::make('bank_account_number'),
                            ]),
                    ]),

                Section::make('Media')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('photo')
                            ->image()
                            ->directory('employees/photos'),
                    ]),
            ]);
    }
}
