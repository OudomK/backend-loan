<?php

namespace App\Filament\Resources\Revenues\Pages;

use App\Filament\Resources\Revenues\RevenueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRevenue extends CreateRecord
{
    protected static string $resource = RevenueResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
