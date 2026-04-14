<?php

namespace App\Filament\Resources\CapitalShares;

use App\Filament\Resources\CapitalShares\Pages\CreateCapitalShare;
use App\Filament\Resources\CapitalShares\Pages\EditCapitalShare;
use App\Filament\Resources\CapitalShares\Pages\ListCapitalShares;
use App\Filament\Resources\CapitalShares\Schemas\CapitalShareForm;
use App\Filament\Resources\CapitalShares\Tables\CapitalSharesTable;
use App\Models\CapitalShare;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

class CapitalShareResource extends Resource
{
    protected static ?string $model = CapitalShare::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string|\UnitEnum|null $navigationGroup = 'Fund Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'account_no';

    protected static ?string $navigationLabel = 'Capital & Shares';

    protected static ?string $modelLabel = 'Capital Share';

    protected static ?string $pluralModelLabel = 'Capital Shares';

    public static function getGloballySearchableAttributes(): array
    {
        return ['account_no', 'investor.first_name', 'investor.last_name', 'investor.customer_code'];
    }

    public static function form(Schema $schema): Schema
    {
        return CapitalShareForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CapitalSharesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('category', 'Real Capital')
            ->with(['investor']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCapitalShares::route('/'),
            'create' => CreateCapitalShare::route('/create'),
            'edit' => EditCapitalShare::route('/{record}/edit'),
        ];
    }

    public static function nextAccountNo(): string
    {
        return 'ACC-' . now()->format('Ymd-His');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeCapitalShareData(array $data, ?CapitalShare $record = null): array
    {
        if ($record && $record->category !== 'Real Capital') {
            throw ValidationException::withMessages([
                'category' => 'Only Real Capital records can be managed from this screen.',
            ]);
        }

        if (blank($data['investor_id'] ?? null)) {
            throw ValidationException::withMessages([
                'investor_id' => 'Investor is required.',
            ]);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than 0.',
            ]);
        }

        $shareQty = (int) ($data['share_qty'] ?? 0);
        if ($shareQty <= 0) {
            throw ValidationException::withMessages([
                'share_qty' => 'Share quantity must be greater than 0.',
            ]);
        }

        if ($record && static::hasCapitalMovements($record)) {
            throw ValidationException::withMessages([
                'amount' => 'This capital account already has transactions. Direct edits are locked to match the frontend flow.',
            ]);
        }

        $parValue = round($amount / $shareQty, 8);
        if ($parValue <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be high enough to derive a valid par value.',
            ]);
        }

        $borrowingDate = filled($data['borrowing_date'] ?? null)
            ? Carbon::parse((string) $data['borrowing_date'])->toDateString()
            : ($record?->borrowing_date ?? Carbon::today()->toDateString());

        return array_merge($data, [
            'account_no' => filled($data['account_no'] ?? null)
                ? (string) $data['account_no']
                : ($record?->account_no ?? static::nextAccountNo()),
            'category' => 'Real Capital',
            'lender_id' => null,
            'amount' => $amount,
            'share_qty' => $shareQty,
            'par_value' => $parValue,
            'total_capital' => $amount,
            'balance' => $amount,
            'dividends' => round((float) ($data['dividends'] ?? $record?->dividends ?? 0), 2),
            'currency' => strtoupper((string) ($data['currency'] ?? $record?->currency ?? 'USD')),
            'status' => (string) ($record?->status ?? 'Active'),
            'borrowing_date' => $borrowingDate,
        ]);
    }

    private static function hasCapitalMovements(CapitalShare $share): bool
    {
        return $share->transactions()
            ->whereIn('transaction_type', ['Deposit', 'Withdrawal', 'Repayment'])
            ->exists();
    }
}


