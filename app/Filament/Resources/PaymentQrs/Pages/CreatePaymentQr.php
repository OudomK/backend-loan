<?php

namespace App\Filament\Resources\PaymentQrs\Pages;

use App\Filament\Resources\PaymentQrs\PaymentQrResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentQr extends CreateRecord
{
    protected static string $resource = PaymentQrResource::class;

    protected static bool $canCreateAnother = false;

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
