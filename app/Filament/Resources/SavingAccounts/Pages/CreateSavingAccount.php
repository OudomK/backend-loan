<?php

namespace App\Filament\Resources\SavingAccounts\Pages;

use App\Filament\Resources\SavingAccounts\SavingAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSavingAccount extends CreateRecord
{
    protected static string $resource = SavingAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SavingAccountResource::normalizeBorrowingData($data);
    }
}
