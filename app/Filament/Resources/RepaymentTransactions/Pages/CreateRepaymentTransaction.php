<?php

namespace App\Filament\Resources\RepaymentTransactions\Pages;

use App\Filament\Resources\RepaymentTransactions\RepaymentTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRepaymentTransaction extends CreateRecord
{
    protected static string $resource = RepaymentTransactionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function canCreateAnother(): bool
    {
        return false;
    }
}
