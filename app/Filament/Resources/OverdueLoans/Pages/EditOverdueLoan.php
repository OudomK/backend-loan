<?php

namespace App\Filament\Resources\OverdueLoans\Pages;

use App\Filament\Resources\OverdueLoans\OverdueLoanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOverdueLoan extends EditRecord
{
    protected static string $resource = OverdueLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
