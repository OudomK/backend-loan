<?php

namespace App\Filament\Resources\PaymentQrs\Pages;

use App\Filament\Resources\PaymentQrs\PaymentQrResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentQrs extends ListRecords
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

    public function getMaxContentWidth(): string|null
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Header actions are configured in PaymentQrsTable
        ];
    }
}
