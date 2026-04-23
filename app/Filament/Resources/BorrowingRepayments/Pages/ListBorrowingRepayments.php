<?php

namespace App\Filament\Resources\BorrowingRepayments\Pages;

use App\Filament\Resources\BorrowingRepayments\BorrowingRepaymentResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListBorrowingRepayments extends ListRecords
{
    protected static string $resource = BorrowingRepaymentResource::class;

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

