<?php

namespace App\Filament\Resources\CoBorrowers\Pages;

use App\Filament\Resources\CoBorrowers\CoBorrowerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoBorrower extends CreateRecord
{
    protected static string $resource = CoBorrowerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CoBorrowerResource::normalizeCoBorrowerData($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function canCreateAnother(): bool
    {
        return false;
    }
}
