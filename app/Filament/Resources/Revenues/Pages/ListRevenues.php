<?php

namespace App\Filament\Resources\Revenues\Pages;

use App\Filament\Resources\Revenues\RevenueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRevenues extends ListRecords
{
    protected static string $resource = RevenueResource::class;

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
