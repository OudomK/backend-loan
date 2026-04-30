<?php

namespace App\Filament\Resources\PaymentQrs\Pages;

use App\Filament\Resources\PaymentQrs\PaymentQrResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentQr extends EditRecord
{
    protected static string $resource = PaymentQrResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
