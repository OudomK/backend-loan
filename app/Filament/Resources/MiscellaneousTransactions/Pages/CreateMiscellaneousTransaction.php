<?php

namespace App\Filament\Resources\MiscellaneousTransactions\Pages;

use App\Filament\Resources\MiscellaneousTransactions\MiscellaneousTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMiscellaneousTransaction extends CreateRecord
{
    protected static string $resource = MiscellaneousTransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MiscellaneousTransactionResource::normalizeTransactionData($data);
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
