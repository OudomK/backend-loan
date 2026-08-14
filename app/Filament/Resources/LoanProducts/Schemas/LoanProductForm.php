<?php

namespace App\Filament\Resources\LoanProducts\Schemas;

use Filament\Forms\Components\Select;
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
                Select::make('repayment_method')
                    ->options([
                        'fixed_daily' => 'Fixed Daily',
                        'fixed_weekly' => 'Fixed Weekly',
                        'fixed_biweekly' => 'Fixed Bi-weekly',
                        'fixed_monthly' => 'Fixed Monthly',
                        'linear_monthly' => 'Linear Monthly',
                        'annuity_monthly' => 'Annuity Monthly',
                        'Balloon' => 'Balloon',
                        'negotiable' => 'Negotiable',
                    ])
                    ->searchable()
                    ->native(false)
                    ->default(null),
            ]);
    }
}
