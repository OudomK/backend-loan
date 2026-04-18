<?php

namespace App\Filament\Resources\Borrowers\Schemas;

use App\Filament\Resources\Borrowers\BorrowerResource;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BorrowerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('customer_type')
                    ->default('Borrower'),

                Section::make('Borrower Profile')
                    ->description('Manage the borrower identity, status, and contact information from one place.')
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
                                    ->default(fn () => BorrowerResource::nextBorrowerCode())
                                    ->dehydrateStateUsing(fn ($state) => blank($state) ? null : trim((string) $state))
                                    ->unique(ignoreRecord: true)
                                    ->required()
                                    ->maxLength(255),
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
                                TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('first_name')
                                    ->label('First Name')
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
                                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y') : null),
                                TextInput::make('age')
                                    ->label('Age')
                                    ->numeric()
                                    ->minValue(18)
                                    ->maxValue(120),
                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->maxLength(30),
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
                                    ->directory('borrowers/photos')
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
