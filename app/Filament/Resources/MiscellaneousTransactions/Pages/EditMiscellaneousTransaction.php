<?php

namespace App\Filament\Resources\MiscellaneousTransactions\Pages;

use App\Filament\Resources\MiscellaneousTransactions\MiscellaneousTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMiscellaneousTransaction extends EditRecord
{
    protected static string $resource = MiscellaneousTransactionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MiscellaneousTransactionResource::normalizeTransactionData($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
