<?php

namespace App\Filament\Resources\CapitalShareTransactions\Pages;

use App\Filament\Resources\CapitalShareTransactions\CapitalShareTransactionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCapitalShareTransactions extends ListRecords
{
    protected static string $resource = CapitalShareTransactionResource::class;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
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

