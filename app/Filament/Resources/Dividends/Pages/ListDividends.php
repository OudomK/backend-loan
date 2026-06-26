<?php

namespace App\Filament\Resources\Dividends\Pages;

use App\Filament\Resources\Dividends\DividendResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDividends extends ListRecords
{
    protected static string $resource = DividendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
