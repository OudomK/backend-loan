<?php

namespace App\Filament\Resources\PaymentQrs\Pages;

use App\Filament\Resources\PaymentQrs\PaymentQrResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentQrs extends ListRecords
{
    protected static string $resource = PaymentQrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
