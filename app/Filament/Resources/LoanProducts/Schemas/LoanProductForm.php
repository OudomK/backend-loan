<?php

namespace App\Filament\Resources\LoanProducts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LoanProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (callable $set, $state) {
                        if ($state) {
                            $set('code', strtoupper(\Illuminate\Support\Str::slug($state, '-')));
                        }
                    }),
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('interest_rate')
                    ->numeric()
                    ->default(null),
                TextInput::make('fee_percentage')
                    ->numeric()
                    ->default(null),
                TextInput::make('duration_months')
                    ->numeric()
                    ->default(null),
                TextInput::make('repayment_method')
                    ->default(null),
            ]);
    }
}
