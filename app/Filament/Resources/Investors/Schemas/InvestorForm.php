<?php

namespace App\Filament\Resources\Investors\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvestorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Investor Identity')
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
                                TextInput::make('customer_code')
                                    ->label('Investor Code')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                Select::make('status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Inactive' => 'Inactive',
                                    ])
                                    ->required()
                                    ->default('Active'),
                                Select::make('customer_type')
                                    ->options([
                                        'Investor' => 'Investor',
                                    ])
                                    ->default('Investor')
                                    ->disabled(),
                            ]),
                    ]),

                Section::make('Contact & KYI')
                    ->description('Contact details and identifying documents.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('phone')
                                    ->tel()
                                    ->prefix('+855'),
                                DatePicker::make('dob')
                                    ->label('Date of Birth')
                                    ->native(false),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Select::make('id_type')
                                    ->options([
                                        'National ID' => 'National ID',
                                        'Passport' => 'Passport',
                                        'Family Book' => 'Family Book',
                                    ]),
                                TextInput::make('id_number'),
                                DatePicker::make('id_expiry')
                                    ->native(false),
                            ]),
                    ]),

                Section::make('Address')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('province'),
                                TextInput::make('district'),
                                TextInput::make('commune'),
                                TextInput::make('village'),
                            ]),
                    ]),

                Section::make('Media')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('photo')
                            ->image()
                            ->directory('investors/photos'),
                    ]),
            ]);
    }
}
