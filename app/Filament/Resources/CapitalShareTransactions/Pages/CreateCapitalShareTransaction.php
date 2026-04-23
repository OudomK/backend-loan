<?php

namespace App\Filament\Resources\CapitalShareTransactions\Pages;

use App\Filament\Resources\CapitalShareTransactions\CapitalShareTransactionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateCapitalShareTransaction extends CreateRecord
{
    protected static string $resource = CapitalShareTransactionResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CapitalShareTransactionResource::normalizeTransactionData($data);
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
