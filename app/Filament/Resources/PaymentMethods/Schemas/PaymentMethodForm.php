<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Payment Method Details')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            TextInput::make('name')
                                ->required(),
                            Toggle::make('is_active')
                                ->required()
                                ->default(true),
                        ])
                    ]),
            ]);
    }
}
