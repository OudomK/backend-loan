<?php

namespace App\Filament\Resources\Translations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
                Textarea::make('en')
                    ->default(null)
                    ->columnSpanFull()
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
                    ->default(null)
                    ->columnSpanFull()
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
                            ->label('Auto Translate (from English)')
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
            ]);
    }
}
