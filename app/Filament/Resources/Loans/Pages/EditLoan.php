<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Models\Loan;
use App\Services\CommissionIncomeService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;

    protected function afterSave(): void
    {
        if ($this->record instanceof Loan) {
            app(CommissionIncomeService::class)->syncForLoan($this->record->fresh());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
