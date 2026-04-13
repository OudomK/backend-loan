<?php

namespace App\Filament\Resources\CoBorrowers\Pages;

use App\Filament\Resources\CoBorrowers\CoBorrowerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoBorrower extends EditRecord
{
    protected static string $resource = CoBorrowerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Models\CoBorrower $record */
        $record = $this->getRecord();

        return CoBorrowerResource::normalizeCoBorrowerData($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
