<?php

namespace App\Filament\Resources\LoanOfficers\Pages;

use App\Filament\Resources\LoanOfficers\LoanOfficerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLoanOfficer extends EditRecord
{
    protected static string $resource = LoanOfficerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Models\LoanOfficer $record */
        $record = $this->getRecord();

        return LoanOfficerResource::normalizeLoanOfficerData($data, $record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
