<?php

namespace App\Filament\Resources\Revenues\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class RevenueInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Revenue Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('transaction_date')
                                    ->label('Date')
                                    ->date('d/m/Y'),
                                TextEntry::make('reference_no')
                                    ->label('Reference No')
                                    ->weight('bold'),
                                TextEntry::make('revenue_category.name')
                                    ->label('Category')
                                    ->badge()
                                    ->color('success'),
                            ]),
                        
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('amount')
                                    ->money(fn ($record) => $record->currency)
                                    ->weight('bold'),
                                TextEntry::make('payment_method')
                                    ->label('Payment Method'),
                                TextEntry::make('loan.loan_code')
                                    ->label('Related Loan')
                                    ->placeholder('N/A'),
                            ]),

                        TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
