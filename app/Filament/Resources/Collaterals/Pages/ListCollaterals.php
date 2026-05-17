<?php

namespace App\Filament\Resources\Collaterals\Pages;

use App\Filament\Resources\CollateralResource;
use Filament\Resources\Pages\ListRecords;

class ListCollaterals extends ListRecords
{
    protected static string $resource = CollateralResource::class;

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getMaxContentWidth(): string|null
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Header actions are handled in the table's headerActions for consistency
        ];
    }
}
