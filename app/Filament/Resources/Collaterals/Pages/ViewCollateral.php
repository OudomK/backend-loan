<?php

namespace App\Filament\Resources\Collaterals\Pages;

use App\Filament\Resources\CollateralResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCollateral extends ViewRecord
{
    protected static string $resource = CollateralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
        ];
    }
}
