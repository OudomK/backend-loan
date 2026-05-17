<?php

namespace App\Filament\Resources\RevenueCategories\Pages;

use App\Filament\Resources\RevenueCategories\RevenueCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRevenueCategory extends CreateRecord
{
    protected static string $resource = RevenueCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
