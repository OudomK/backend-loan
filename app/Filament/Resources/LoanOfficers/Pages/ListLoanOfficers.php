<?php

namespace App\Filament\Resources\LoanOfficers\Pages;

use App\Filament\Resources\LoanOfficers\LoanOfficerResource;
use Filament\Resources\Pages\ListRecords;

class ListLoanOfficers extends ListRecords
{
    protected static string $resource = LoanOfficerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
