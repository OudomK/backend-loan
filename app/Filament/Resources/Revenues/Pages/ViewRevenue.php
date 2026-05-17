<?php

namespace App\Filament\Resources\Revenues\Pages;

use App\Filament\Resources\Revenues\RevenueResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRevenue extends ViewRecord
{
    protected static string $resource = RevenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
