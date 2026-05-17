<?php

namespace App\Filament\Resources\RevenueCategories\Pages;

use App\Filament\Resources\RevenueCategories\RevenueCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListRevenueCategories extends ListRecords
{
    protected static string $resource = RevenueCategoryResource::class;

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getMaxContentWidth(): string|null
    {
        return 'full';
    }
}
