<?php

namespace App\Filament\Resources\Collaterals\Pages;

use App\Filament\Resources\CollateralResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCollateral extends CreateRecord
{
    protected static string $resource = CollateralResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
