<?php

namespace App\Filament\Resources\Translations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Translation Content')
                    ->description('Manage translation key and localized texts.')
                    ->icon('heroicon-o-language')
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->prefixIcon('heroicon-o-key')
                            ->columnSpanFull(),
                        Textarea::make('en')
                            ->label('English Text')
                            ->default(null)
                            ->columnSpan(1)
                            ->rows(5)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, $set, $get) {
                                if (!empty($state) && empty($get('kh'))) {
                                    try {
                                        $translated = GoogleTranslate::trans($state, 'km', 'en');
                                        $set('kh', $translated);
                                    } catch (\Exception $e) {}
                                }
                            }),
                        Textarea::make('kh')
                            ->label('Khmer Text')
                            ->default(null)
                            ->columnSpan(1)
                            ->rows(5)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, $set, $get) {
                                if (!empty($state) && empty($get('en'))) {
                                    try {
                                        $translated = GoogleTranslate::trans($state, 'en', 'km');
                                        $set('en', $translated);
                                    } catch (\Exception $e) {}
                                }
                            })
                            ->hintAction(
                                Action::make('translate')
                                    ->label('Auto Translate (from EN)')
                                    ->icon('heroicon-m-language')
                                    ->action(function ($set, $get) {
                                        $enText = $get('en');
                                        if (!empty($enText)) {
                                            try {
                                                $translated = GoogleTranslate::trans($enText, 'km', 'en');
                                                $set('kh', $translated);
                                            } catch (\Exception $e) {}
                                        }
                                    })
                            ),
                    ])
                    ->columns(2),
            ]);
    }
}
