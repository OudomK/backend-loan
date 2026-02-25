<?php

namespace App\Filament\Resources\CoBorrowers\Pages;

use App\Filament\Resources\CoBorrowers\CoBorrowerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoBorrowers extends ListRecords
{
    protected static string $resource = CoBorrowerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
