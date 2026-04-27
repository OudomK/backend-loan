<?php

namespace App\Filament\Resources\PaymentQrs\Pages;

use App\Filament\Resources\PaymentQrs\PaymentQrResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentQr extends CreateRecord
{
    protected static string $resource = PaymentQrResource::class;
}
