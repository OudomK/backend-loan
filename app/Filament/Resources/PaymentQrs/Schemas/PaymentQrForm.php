<?php

namespace App\Filament\Resources\PaymentQrs\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentQrForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                FileUpload::make('image_path')
                    ->disk('public')
                    ->image()
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
