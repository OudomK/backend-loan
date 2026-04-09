<?php

namespace App\Filament\Resources\SavingAccounts\Pages;

use App\Filament\Resources\SavingAccounts\SavingAccountResource;
use Filament\Resources\Pages\EditRecord;

class EditSavingAccount extends EditRecord
{
    protected static string $resource = SavingAccountResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Models\Borrowing $record */
        $record = $this->getRecord();

        return SavingAccountResource::normalizeBorrowingData($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
