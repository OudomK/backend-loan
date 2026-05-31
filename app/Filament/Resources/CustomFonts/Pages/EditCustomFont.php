<?php

namespace App\Filament\Resources\CustomFonts\Pages;

use App\Filament\Resources\CustomFonts\CustomFontResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomFont extends EditRecord
{
    protected static string $resource = CustomFontResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
