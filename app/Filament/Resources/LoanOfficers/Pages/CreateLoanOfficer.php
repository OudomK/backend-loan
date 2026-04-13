<?php

namespace App\Filament\Resources\LoanOfficers\Pages;

use App\Filament\Resources\LoanOfficers\LoanOfficerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoanOfficer extends CreateRecord
{
    protected static string $resource = LoanOfficerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return LoanOfficerResource::normalizeLoanOfficerData($data);
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
