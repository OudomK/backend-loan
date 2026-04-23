<?php

namespace App\Filament\Resources\BorrowingRepayments\Pages;

use App\Filament\Resources\BorrowingRepayments\BorrowingRepaymentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditBorrowingRepayment extends EditRecord
{
    protected static string $resource = BorrowingRepaymentResource::class;

    protected ?int $originalBorrowingId = null;
    protected Width|string|null $maxContentWidth = 'full';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->originalBorrowingId = (int) ($this->record->borrowing_id ?? 0);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalBorrowingId = (int) ($this->record->borrowing_id ?? 0);

        return BorrowingRepaymentResource::normalizeRepaymentData($data, $this->record);
    }

    protected function afterSave(): void
    {
        BorrowingRepaymentResource::syncBorrowingStatus(
            $this->record?->borrowing_id ? (int) $this->record->borrowing_id : null
        );

        if ($this->originalBorrowingId && $this->originalBorrowingId !== (int) $this->record->borrowing_id) {
            BorrowingRepaymentResource::syncBorrowingStatus($this->originalBorrowingId);
        }
    }

    protected function afterDelete(): void
    {
        BorrowingRepaymentResource::syncBorrowingStatus(
            $this->originalBorrowingId ?: (int) ($this->record->borrowing_id ?? 0)
        );
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
