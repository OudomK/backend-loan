<?php

namespace App\Filament\Resources\CustomFonts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomFontForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Font File Details')
                    ->description('Upload custom font files (.ttf or .otf) to import into the system.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Preah Vihear')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('key', strtolower(str_replace(' ', '_', $state)))),
                            
                        TextInput::make('key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g. preah_vihear')
                            ->maxLength(255),
                            
                        Toggle::make('is_active')
                            ->required()
                            ->default(true),
                            
                        FileUpload::make('file_path')
                            ->label('Font File (.ttf, .otf)')
                            ->disk('public')
                            ->directory('custom-fonts')
                            ->acceptedFileTypes(['font/ttf', 'font/otf', 'application/x-font-truetype', 'application/x-font-opentype', 'font/sfnt'])
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
