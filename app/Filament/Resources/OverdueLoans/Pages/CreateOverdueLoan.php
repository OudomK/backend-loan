<?php

namespace App\Filament\Resources\OverdueLoans\Pages;

use App\Filament\Resources\OverdueLoans\OverdueLoanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOverdueLoan extends CreateRecord
{
    protected static string $resource = OverdueLoanResource::class;
}
