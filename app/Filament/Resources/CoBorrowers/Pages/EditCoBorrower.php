<?php

namespace App\Filament\Resources\CoBorrowers\Pages;

use App\Filament\Resources\CoBorrowers\CoBorrowerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoBorrower extends EditRecord
{
    protected static string $resource = CoBorrowerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
