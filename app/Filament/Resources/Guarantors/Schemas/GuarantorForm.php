<?php

namespace App\Filament\Resources\Guarantors\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GuarantorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('Primary details about the guarantor.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('first_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('last_name')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('gender')
                                    ->options([
                                        'Male' => 'Male',
                                        'Female' => 'Female',
                                        'Other' => 'Other',
                                    ]),
                            ]),
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('dob')
                                    ->label('Date of Birth')
                                    ->native(false),
                                TextInput::make('age')
                                    ->numeric()
                                    ->minValue(18)
                                    ->maxValue(120),
                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->prefix('+855'),
                            ]),
                    ]),

                Section::make('Identification')
                    ->description('Identity documents.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('customer_code')
                                    ->label('Customer Code')
                                    ->placeholder('Enter unique code')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Inactive' => 'Inactive',
                                        'Blacklisted' => 'Blacklisted',
                                    ])
                                    ->required()
                                    ->default('Active'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Select::make('id_type')
                                    ->label('ID Type')
                                    ->options([
                                        'National ID' => 'National ID',
                                        'Passport' => 'Passport',
                                        'Family Book' => 'Family Book',
                                    ]),
                                TextInput::make('id_number')
                                    ->label('ID Number'),
                                DatePicker::make('id_expiry')
                                    ->label('ID Expiry Date')
                                    ->native(false),
                            ]),
                    ]),

                Section::make('Address & Employment')
                    ->description('Where the guarantor lives and works.')
                    ->icon('heroicon-o-home')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('occupation')
                                    ->maxLength(255),
                                TextInput::make('village'),
                                TextInput::make('commune'),
                                TextInput::make('district'),
                                TextInput::make('province'),
                            ]),
                    ]),

                Section::make('Media')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('photo')
                            ->image()
                            ->imageEditor()
                            ->directory('guarantors/photos')
                            ->visibility('public'),
                    ]),
            ]);
    }
}
