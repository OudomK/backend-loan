<?php

namespace App\Filament\Resources\Dividends\Pages;

use App\Filament\Resources\Dividends\DividendResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDividend extends CreateRecord
{
    protected static string $resource = DividendResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $totalSharesCount = (float) $data['total_shares_count'];

        if ($data['distribution_basis'] === 'total') {
            $data['dividend_per_share'] = round((float) $data['total_amount'] / $totalSharesCount, 4);
        } else {
            $data['total_amount'] = round((float) $data['dividend_per_share'] * $totalSharesCount, 2);
        }

        $data['tax_amount'] = 0.0;
        $data['net_amount'] = round((float) $data['total_amount'] - $data['tax_amount'], 2);
        $data['status'] = 'Draft';
        $data['declared_by'] = \Illuminate\Support\Facades\Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $dividend = $this->record;
        $shares = \App\Models\CapitalShare::where('currency', $dividend->currency)
            ->where('share_qty', '>', 0)
            ->get();

        foreach ($shares as $share) {
            \App\Models\DividendTransaction::create([
                'dividend_id' => $dividend->id,
                'capital_share_id' => $share->id,
                'amount' => $share->share_qty * $dividend->dividend_per_share,
                'currency' => $dividend->currency,
                'status' => 'Pending',
            ]);
        }
    }
}
