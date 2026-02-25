<?php

namespace App\Filament\Resources\Guarantors\Pages;

use App\Filament\Resources\Guarantors\GuarantorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuarantors extends ListRecords
{
    protected static string $resource = GuarantorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
