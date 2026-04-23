<?php

namespace App\Filament\Resources\CapitalShareTransactions\Pages;

use App\Filament\Resources\CapitalShareTransactions\CapitalShareTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditCapitalShareTransaction extends EditRecord
{
    protected static string $resource = CapitalShareTransactionResource::class;
    protected Width|string|null $maxContentWidth = 'full';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CapitalShareTransactionResource::normalizeTransactionData($data);
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
