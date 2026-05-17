<?php

namespace App\Filament\Resources\Collaterals\Pages;

use App\Filament\Resources\CollateralResource;
use Filament\Resources\Pages\EditRecord;

class EditCollateral extends EditRecord
{
    protected static string $resource = CollateralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ViewAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
