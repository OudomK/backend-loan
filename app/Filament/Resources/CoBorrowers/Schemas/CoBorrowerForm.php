<?php

namespace App\Filament\Resources\CoBorrowers\Schemas;

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
                Section::make('Personal Information')
                    ->description('Primary details about the co-borrower.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('last_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('first_name')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('gender')
                                    ->options([
                                        'Male' => 'Male',
                                        'Female' => 'Female',
                                        'Other' => 'Other',
                                    ]),
                                Select::make('marital_status')
                                    ->options([
                                        'Single' => 'Single',
                                        'Married' => 'Married',
                                        'Divorced' => 'Divorced',
                                        'Widowed' => 'Widowed',
                                    ]),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('dob')
                                    ->label('Date of Birth')
                                    ->placeholder('dd/mm/yyyy')
                                    ->helperText('Format: dd/mm/yyyy')
                                    ->mask('99/99/9999')
                                    ->rule('date_format:d/m/Y')
                                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),
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
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->default(function () {
                                        $latest = \App\Models\CoBorrower::orderBy('id', 'desc')->first();
                                        $nextId = $latest ? $latest->id + 1 : 1;
                                        return 'QF-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
                                    }),
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
                                    ->label('ID Number')
                                    ->unique(ignoreRecord: true),
                                TextInput::make('id_expiry')
                                    ->label('ID Expiry Date')
                                    ->placeholder('dd/mm/yyyy')
                                    ->helperText('Format: dd/mm/yyyy')
                                    ->mask('99/99/9999')
                                    ->rule('date_format:d/m/Y')
                                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),
                            ]),
                    ]),

                Section::make('Address & Employment')
                    ->description('Where the co-borrower lives and works.')
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
                                FileUpload::make('photo')
                                    ->image()
                                    ->imageEditor()
                                    ->directory('co-borrowers/photos')
                                    ->visibility('public')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
