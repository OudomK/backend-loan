<?php

namespace App\Filament\Resources\CapitalShares\Pages;

use App\Filament\Resources\CapitalShares\CapitalShareResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateCapitalShare extends CreateRecord
{
    protected static string $resource = CapitalShareResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $category = (string) ($data['category'] ?? 'Real Capital');
        $shareQty = (int) ($data['share_qty'] ?? 0);
        $parValue = round((float) ($data['par_value'] ?? 0), 8);
        $computedTotal = round($shareQty * $parValue, 2);

        $data['share_qty'] = $shareQty;
        $data['par_value'] = $parValue;

        if ($category === 'Real Capital') {
            if (empty($data['investor_id'])) {
                throw ValidationException::withMessages([
                    'investor_id' => 'Investor is required for Real Capital.',
                ]);
            }

            if ($computedTotal <= 0) {
                throw ValidationException::withMessages([
                    'total_capital' => 'Share quantity and par value must be greater than 0.',
                ]);
            }

            $data['lender_id'] = null;
            $data['total_capital'] = $computedTotal;
            $data['amount'] = $computedTotal;
            $data['balance'] = round((float) ($data['balance'] ?? $computedTotal), 2);
        } else {
            if (empty($data['lender_id'])) {
                throw ValidationException::withMessages([
                    'lender_id' => 'Lender is required for Loan Capital.',
                ]);
            }

            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Loan Amount must be greater than 0.',
                ]);
            }

            $data['investor_id'] = null;
            if ($data['par_value'] <= 0) {
                $data['par_value'] = 1.0;
            }
            if ($data['share_qty'] <= 0) {
                $data['share_qty'] = (int) max(1, floor($amount / $data['par_value']));
            }
            $data['total_capital'] = $amount;
            $data['amount'] = $amount;
            $data['balance'] = round((float) ($data['balance'] ?? $amount), 2);
        }

        $data['status'] = (string) ($data['status'] ?? 'Active');
        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'USD'));

        return $data;
    }
}
