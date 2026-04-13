<?php

namespace App\Filament\Resources\Guarantors\Pages;

use App\Filament\Resources\Guarantors\GuarantorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGuarantor extends EditRecord
{
    protected static string $resource = GuarantorResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Models\Guarantor $record */
        $record = $this->getRecord();

        return GuarantorResource::normalizeGuarantorData($data, $record);
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
