<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    protected ?string $subheading = 'Manage loan records with quick access to status, rate, and duration details.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Loan')
                ->icon('heroicon-m-plus-circle')
                ->button(),
        ];
    }

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }
}
