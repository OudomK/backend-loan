<?php

namespace App\Filament\Resources\OverdueLoans\Pages;

use App\Filament\Resources\OverdueLoans\OverdueLoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOverdueLoans extends ListRecords
{
    protected static string $resource = OverdueLoanResource::class;

    public function getTitle(): string
    {
        return 'Overdue Payments';
    }

    public function getHeading(): string|null
    {
        return null;
    }

    public function getSubheading(): string|null
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
        return [];
    }
}
