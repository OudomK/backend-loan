<?php

namespace App\Filament\Resources\RepaymentTransactions\Pages;

use App\Filament\Resources\RepaymentTransactions\RepaymentTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRepaymentTransaction extends EditRecord
{
    protected static string $resource = RepaymentTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
