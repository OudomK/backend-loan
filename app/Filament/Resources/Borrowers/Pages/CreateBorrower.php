<?php

namespace App\Filament\Resources\Borrowers\Pages;

use App\Filament\Resources\Borrowers\BorrowerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBorrower extends CreateRecord
{
    protected static string $resource = BorrowerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return BorrowerResource::normalizeBorrowerData($data);
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
