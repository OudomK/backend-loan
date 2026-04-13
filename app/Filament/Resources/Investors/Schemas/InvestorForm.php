<?php

namespace App\Filament\Resources\Investors\Schemas;

use App\Filament\Resources\Investors\InvestorResource;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
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
                Hidden::make('customer_type')
                    ->default('Investor'),

                Hidden::make('status')
                    ->default('Active'),

                Section::make('Investor Registration')
                    ->description('Use the same fields, order, and defaults as the frontend investor registration flow.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('customer_code')
                                    ->label('Customer Code')
                                    ->helperText('Auto-generated if empty.')
                                    ->default(fn () => InvestorResource::nextInvestorCode())
                                    ->dehydrateStateUsing(fn ($state) => blank($state) ? null : trim((string) $state))
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
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
                                    ])
                                    ->placeholder('Select marital status'),
                            ]),
                        Grid::make(1)
                            ->schema([
                                TextInput::make('dob')
                                    ->label('DOB')
                                    ->placeholder('DD/MM/YYYY')
                                    ->helperText('Format: DD/MM/YYYY')
                                    ->mask('99/99/9999')
                                    ->rule('date_format:d/m/Y')
                                    ->formatStateUsing(fn ($state) => self::formatDateForDisplay($state)),
                            ]),
                        Grid::make(1)
                            ->schema([
                                TextInput::make('age')
                                    ->label('Age')
                                    ->numeric(),
                            ]),
                        Grid::make(1)
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->required()
                                    ->maxLength(30),
                            ]),
                        Grid::make(2)
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
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(100),
                            ]),
                        Grid::make(1)
                            ->schema([
                                TextInput::make('id_expiry')
                                    ->label('Identity Expiry')
                                    ->placeholder('DD/MM/YYYY')
                                    ->helperText('Format: DD/MM/YYYY')
                                    ->mask('99/99/9999')
                                    ->rule('date_format:d/m/Y')
                                    ->formatStateUsing(fn ($state) => self::formatDateForDisplay($state)),
                            ]),
                        Grid::make(1)
                            ->schema([
                                TextInput::make('occupation')
                                    ->label('Occupation')
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make('Address')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('village')
                                    ->label('Village')
                                    ->maxLength(255),
                                TextInput::make('commune')
                                    ->label('Commune')
                                    ->maxLength(255),
                                TextInput::make('district')
                                    ->label('District')
                                    ->maxLength(255),
                                TextInput::make('province')
                                    ->label('Province')
                                    ->maxLength(255),
                                FileUpload::make('photo')
                                    ->image()
                                    ->imageEditor()
                                    ->directory('investors/photos')
                                    ->visibility('public')
                                    ->columnSpanFull(),
                            ]),
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
