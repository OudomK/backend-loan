<?php

namespace App\Filament\Resources\Guarantors\Pages;

use App\Filament\Resources\Guarantors\GuarantorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuarantor extends CreateRecord
{
    protected static string $resource = GuarantorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return GuarantorResource::normalizeGuarantorData($data);
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
