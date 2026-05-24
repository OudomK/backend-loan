<?php

namespace App\Filament\Resources\LoanProducts\Pages;

use App\Filament\Resources\LoanProducts\LoanProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoanProducts extends ListRecords
{
    protected static string $resource = LoanProductResource::class;

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
