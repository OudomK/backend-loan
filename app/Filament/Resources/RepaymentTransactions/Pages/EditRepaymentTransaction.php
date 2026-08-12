<?php

namespace App\Filament\Resources\RepaymentTransactions\Pages;

use App\Filament\Resources\RepaymentTransactions\RepaymentTransactionResource;
use App\Services\RepaymentService;
use App\Services\RepaymentTransactionEditService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditRepaymentTransaction extends EditRecord
{
    protected static string $resource = RepaymentTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('void')
                ->label('Void Transaction')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will reverse the repayment from the loan schedule and mark the transaction as voided.')
                ->hidden(fn (): bool => (bool) $this->getRecord()->trashed())
                ->action(function (): void {
                    try {
                        app(RepaymentService::class)->void($this->getRecord());

                        Notification::make()
                            ->success()
                            ->title('Transaction voided successfully')
                            ->send();

                        $this->redirect($this->getResource()::getUrl('index'));
                    } catch (\RuntimeException $exception) {
                        Notification::make()
                            ->danger()
                            ->title($exception->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Save changes'),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $updatedRecord = app(RepaymentTransactionEditService::class)->update($record, $data);
            $this->record = $updatedRecord;

            return $updatedRecord;
        } catch (\RuntimeException $exception) {
            Notification::make()
                ->danger()
                ->title($exception->getMessage())
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Repayment transaction updated successfully';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
