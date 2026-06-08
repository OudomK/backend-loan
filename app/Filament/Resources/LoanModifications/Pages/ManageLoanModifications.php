<?php

namespace App\Filament\Resources\LoanModifications\Pages;

use App\Filament\Resources\LoanModifications\LoanModificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageLoanModifications extends ManageRecords
{
    protected static string $resource = LoanModificationResource::class;

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

    protected function getTableHeaderActions(): array
    {
        return [];
    }

    public function getMaxContentWidth(): string|null
    {
        return 'full';
    }
}
