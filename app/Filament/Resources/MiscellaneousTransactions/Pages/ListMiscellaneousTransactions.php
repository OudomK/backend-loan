<?php

namespace App\Filament\Resources\MiscellaneousTransactions\Pages;

use App\Filament\Resources\MiscellaneousTransactions\MiscellaneousTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListMiscellaneousTransactions extends ListRecords
{
    protected static string $resource = MiscellaneousTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
