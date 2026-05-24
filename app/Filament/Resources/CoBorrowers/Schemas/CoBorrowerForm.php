<?php

namespace App\Filament\Resources\CoBorrowers\Schemas;

use App\Filament\Resources\CoBorrowers\CoBorrowerResource;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CoBorrowerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Co-Borrower Profile')
                    ->description('Manage the co-borrower identity, status, and contact information from one place.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('customer_code')
                                    ->label('Customer Code')
                                    ->helperText('Auto-generated if empty.')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->default(fn () => CoBorrowerResource::nextCoBorrowerCode()),
                                Select::make('status')
                                    ->label('Status')
                                    ->native(false)
                                    ->options([
                                        'Active' => 'Active',
                                        'Inactive' => 'Inactive',
                                        'Blacklisted' => 'Blacklisted',
                                    ])
                                    ->required()
                                    ->default('Active'),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                            ->schema([
                                TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('gender')
                                    ->native(false)
                                    ->options([
                                        'Male' => 'Male',
                                        'Female' => 'Female',
                                        'Other' => 'Other',
                                    ]),
                                Select::make('marital_status')
                                    ->label('Marital Status')
                                    ->native(false)
                                    ->options([
                                        'Single' => 'Single',
                                        'Married' => 'Married',
                                        'Divorced' => 'Divorced',
                                        'Widowed' => 'Widowed',
                                    ]),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])
                            ->schema([
                                TextInput::make('dob')
                                    ->label('DOB')
                                    ->placeholder('DD/MM/YYYY')
                                    ->helperText('Format: DD/MM/YYYY')
                                    ->mask('99/99/9999')
                                    ->rule('date_format:d/m/Y')
                                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y') : null)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (blank($state) || strlen($state) < 10) {
                                            return;
                                        }

                                        try {
                                            $date = Carbon::createFromFormat('d/m/Y', $state);
                                            $set('age', $date->age);
                                        } catch (\Exception $e) {
                                        }
                                    }),
                                TextInput::make('age')
                                    ->label('Age')
                                    ->numeric()
                                    ->minValue(18)
                                    ->maxValue(120)
                                    ->readOnly(),
                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->maxLength(60)
                                    ->helperText('You can enter up to 3 phone numbers, separated by /, comma, semicolon, or a new line.'),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])
                            ->schema([
                                Select::make('id_type')
                                    ->label('ID Type')
                                    ->native(false)
                                    ->options([
                                        'National ID' => 'National ID',
                                        'Passport' => 'Passport',
                                        'Family Book' => 'Family Book',
                                        'Birth Certificate' => 'Birth Certificate',
                                        'Driving License' => 'Driving License',
                                    ]),
                                TextInput::make('id_number')
                                    ->label('ID Number')
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),
                                TextInput::make('id_expiry')
                                    ->label('Identity Expiry')
                                    ->placeholder('DD/MM/YYYY')
                                    ->helperText('Format: DD/MM/YYYY')
                                    ->mask('99/99/9999')
                                    ->rule('date_format:d/m/Y')
                                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y') : null),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('occupation')
                                    ->label('Occupation')
                                    ->maxLength(255),
                                FileUpload::make('photo')
                                    ->label('Photo')
                                    ->image()
                                    ->imageEditor()
                                    ->directory('co-borrowers/photos')
                                    ->visibility('public')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Address')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('village'),
                                TextInput::make('commune'),
                                TextInput::make('district'),
                                TextInput::make('province'),
                            ]),
                    ]),
            ]);
    }
}
