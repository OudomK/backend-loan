<?php

namespace App\Filament\Resources\Investors\Pages;

use App\Filament\Resources\Investors\InvestorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvestor extends CreateRecord
{
    protected static string $resource = InvestorResource::class;

    public function getHeading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}

