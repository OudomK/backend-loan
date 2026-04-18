<?php

namespace App\Filament\Resources\Investors\Schemas;

use App\Filament\Resources\Investors\InvestorResource;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvestorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('customer_type')
                    ->default('Investor'),

                Grid::make([
                    'default' => 1,
                    'lg' => 2,
                ])
                    ->columnSpan('full')
                    ->schema([
                        Group::make()
                            ->schema([
                                Section::make('Personal Information')
                                    ->description('Basic identity and contact details.')
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('customer_code')
                                                    ->label('Customer Code')
                                                    ->helperText('Auto-generated if empty.')
                                                    ->default(fn () => InvestorResource::nextInvestorCode())
                                                    ->unique(ignoreRecord: true)
                                                    ->columnSpan(2),
                                                Select::make('status')
                                                    ->label('Status')
                                                    ->native(false)
                                                    ->options([
                                                        'Active' => 'Active',
                                                        'Inactive' => 'Inactive',
                                                    ])
                                                    ->default('Active')
                                                    ->required(),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('last_name')
                                                    ->label('Last Name')
                                                    ->required()
                                                    ->maxLength(255),
                                                TextInput::make('first_name')
                                                    ->label('First Name')
                                                    ->required()
                                                    ->maxLength(255),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
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

                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('dob')
                                                    ->label('DOB')
                                                    ->placeholder('DD/MM/YYYY')
                                                    ->mask('99/99/9999')
                                                    ->rule('date_format:d/m/Y')
                                                    ->formatStateUsing(fn ($state) => self::formatDateForDisplay($state)),
                                                TextInput::make('age')
                                                    ->label('Age')
                                                    ->numeric(),
                                                TextInput::make('phone')
                                                    ->label('Phone')
                                                    ->tel()
                                                    ->required(),
                                            ]),
                                    ]),

                                Section::make('Professional Details')
                                    ->icon('heroicon-o-briefcase')
                                    ->compact()
                                    ->schema([
                                        TextInput::make('occupation')
                                            ->label('Occupation')
                                            ->maxLength(255),
                                        FileUpload::make('photo')
                                            ->label('Profile Photo')
                                            ->image()
                                            ->imageEditor()
                                            ->directory('investors/photos')
                                            ->visibility('public')
                                            ->columnSpanFull(),
                                    ]),
                            ])->columnSpan(1),

                        Group::make()
                            ->schema([
                                Section::make('Identity Documents')
                                    ->description('Official identification details.')
                                    ->icon('heroicon-o-identification')
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
                                            ])
                                            ->columnSpanFull(),
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('id_number')
                                                    ->label('ID Number')
                                                    ->required()
                                                    ->unique(ignoreRecord: true),
                                                TextInput::make('id_expiry')
                                                    ->label('Expiry Date')
                                                    ->placeholder('DD/MM/YYYY')
                                                    ->mask('99/99/9999')
                                                    ->rule('date_format:d/m/Y')
                                                    ->formatStateUsing(fn ($state) => self::formatDateForDisplay($state)),
                                            ]),
                                    ]),

                                Section::make('Address & Location')
                                    ->description('Permanent residence details.')
                                    ->icon('heroicon-o-map-pin')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('village')
                                                    ->label('Village'),
                                                TextInput::make('commune')
                                                    ->label('Commune'),
                                                TextInput::make('district')
                                                    ->label('District'),
                                                TextInput::make('province')
                                                    ->label('Province'),
                                            ]),
                                    ]),
                            ])->columnSpan(1),
                    ]),
            ]);
    }

    private static function formatDateForDisplay(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $raw = trim((string) $state);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);

                if ($date !== false && $date->format($format) === $raw) {
                    return $date->format('d/m/Y');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable) {
            return $raw;
        }
    }
}
