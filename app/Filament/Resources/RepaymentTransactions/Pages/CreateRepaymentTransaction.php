<?php

namespace App\Filament\Resources\RepaymentTransactions\Pages;

use App\Filament\Resources\RepaymentTransactions\RepaymentTransactionResource;
use App\Services\RepaymentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateRepaymentTransaction extends CreateRecord
{
    protected static string $resource = RepaymentTransactionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $result = app(RepaymentService::class)->process([
                'loan_id' => $data['loan_id'],
                'collector_id' => $data['collector_id'],
                'amount_paid' => $data['amount_paid'],
                'payment_method' => $data['payment_method'],
                'repayment_type' => $data['repayment_type'],
                'transaction_date' => $data['transaction_date'],
                'penalty_amount' => $data['penalty_paid'] ?? 0,
                'penalty_due' => ($data['penalty_paid'] ?? 0) + ($data['waived_amount'] ?? 0),
                'fee_amount' => $data['fee_paid'] ?? 0,
                'waived_amount' => $data['waived_amount'] ?? 0,
            ]);
        } catch (\RuntimeException $exception) {
            Notification::make()
                ->danger()
                ->title($exception->getMessage())
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }

        return $result['transaction'];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Repayment processed successfully';
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
