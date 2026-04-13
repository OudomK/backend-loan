<?php

namespace App\Filament\Resources\CapitalShares\Pages;

use App\Filament\Resources\CapitalShares\CapitalShareResource;
use App\Models\CapitalShare;
use App\Models\CapitalShareTransaction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCapitalShare extends CreateRecord
{
    protected static string $resource = CapitalShareResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CapitalShareResource::normalizeCapitalShareData($data);
    }

    protected function afterCreate(): void
    {
        if (!$this->record instanceof CapitalShare) {
            return;
        }

        CapitalShareTransaction::create([
            'capital_share_id' => $this->record->id,
            'transaction_type' => 'Initial',
            'amount' => (float) $this->record->amount,
            'share_qty' => (int) $this->record->share_qty,
            'payment_method' => null,
            'transaction_date' => $this->record->borrowing_date ?? now(),
            'description' => 'Initial capital purchase',
            'performed_by' => Auth::id(),
        ]);
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
