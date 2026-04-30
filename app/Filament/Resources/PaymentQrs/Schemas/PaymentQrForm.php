<?php

namespace App\Filament\Resources\PaymentQrs\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentQrForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('QR Code Information')
                    ->description('Provide the name and upload the QR code image.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Active Status')
                            ->required()
                            ->default(true),
                        FileUpload::make('image_path')
                            ->label('QR Code Image')
                            ->disk('public')
                            ->image()
                            ->imageEditor()
                            ->directory('payment-qrs')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
