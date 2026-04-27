<?php

namespace App\Filament\Resources\BorrowingRepayments\Pages;

use App\Filament\Resources\BorrowingRepayments\BorrowingRepaymentResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateBorrowingRepayment extends CreateRecord
{
    protected static string $resource = BorrowingRepaymentResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return BorrowingRepaymentResource::normalizeRepaymentData($data);
    }

    protected function afterCreate(): void
    {
        BorrowingRepaymentResource::rebuildBorrowingSchedules(
            $this->record?->borrowing_id ? (int) $this->record->borrowing_id : null
        );

        BorrowingRepaymentResource::syncBorrowingStatus(
            $this->record?->borrowing_id ? (int) $this->record->borrowing_id : null
        );
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
