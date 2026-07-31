<?php

namespace App\Filament\Resources\IdTypes\Pages;

use App\Filament\Resources\IdTypes\IdTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIdType extends EditRecord
{
    protected static string $resource = IdTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
