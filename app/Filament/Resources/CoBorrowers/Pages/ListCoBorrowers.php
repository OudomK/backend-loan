<?php

namespace App\Filament\Resources\CoBorrowers\Pages;

use App\Filament\Resources\CoBorrowers\CoBorrowerResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCoBorrowers extends ListRecords
{
    protected static string $resource = CoBorrowerResource::class;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string>
     */
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
