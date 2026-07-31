<?php

namespace App\Filament\Resources\IdTypes\Pages;

use App\Filament\Resources\IdTypes\IdTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIdTypes extends ListRecords
{
    protected static string $resource = IdTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
