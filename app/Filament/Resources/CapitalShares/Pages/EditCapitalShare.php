<?php

namespace App\Filament\Resources\CapitalShares\Pages;

use App\Filament\Resources\CapitalShares\CapitalShareResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCapitalShare extends EditRecord
{
    protected static string $resource = CapitalShareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Models\CapitalShare $record */
        $record = $this->getRecord();

        $data['investor_code_preview'] = $record->investor?->customer_code;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Models\CapitalShare $record */
        $record = $this->getRecord();

        return CapitalShareResource::normalizeCapitalShareData($data, $record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
