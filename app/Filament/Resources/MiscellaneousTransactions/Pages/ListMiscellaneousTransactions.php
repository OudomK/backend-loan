<?php

namespace App\Filament\Resources\MiscellaneousTransactions\Pages;

use App\Filament\Resources\MiscellaneousTransactions\MiscellaneousTransactionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListMiscellaneousTransactions extends ListRecords
{
    protected static string $resource = MiscellaneousTransactionResource::class;

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
