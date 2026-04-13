<?php

namespace App\Filament\Resources\MiscellaneousTransactions;

use App\Filament\Resources\MiscellaneousTransactions\Pages\CreateMiscellaneousTransaction;
use App\Filament\Resources\MiscellaneousTransactions\Pages\EditMiscellaneousTransaction;
use App\Filament\Resources\MiscellaneousTransactions\Pages\ListMiscellaneousTransactions;
use App\Filament\Resources\MiscellaneousTransactions\Schemas\MiscellaneousTransactionForm;
use App\Filament\Resources\MiscellaneousTransactions\Tables\MiscellaneousTransactionsTable;
use App\Models\MiscellaneousTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

class MiscellaneousTransactionResource extends Resource
{
    protected static ?string $model = MiscellaneousTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'HR & Payroll';

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'description';

    public static function getGloballySearchableAttributes(): array
    {
        return ['description', 'category', 'currency', 'amount'];
    }

    public static function form(Schema $schema): Schema
    {
        return MiscellaneousTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MiscellaneousTransactionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
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
            'index' => ListMiscellaneousTransactions::route('/'),
            'create' => CreateMiscellaneousTransaction::route('/create'),
            'edit' => EditMiscellaneousTransaction::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeTransactionData(array $data): array
    {
        $type = strtolower((string) ($data['type'] ?? ''));
        if ($type === 'income') {
            $type = 'revenue';
        }

        if (!in_array($type, ['revenue', 'expense'], true)) {
            throw ValidationException::withMessages([
                'type' => 'Transaction type must be Revenue or Expense.',
            ]);
        }

        $category = trim((string) ($data['category'] ?? ''));
        if ($category === '') {
            throw ValidationException::withMessages([
                'category' => 'Category / Name is required.',
            ]);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than 0.',
            ]);
        }

        $currency = strtoupper(trim((string) ($data['currency'] ?? 'USD')));
        if (!in_array($currency, ['USD', 'KHR'], true)) {
            throw ValidationException::withMessages([
                'currency' => 'Currency must be USD or KHR.',
            ]);
        }

        return array_merge($data, [
            'type' => $type,
            'category' => $category,
            'amount' => $amount,
            'currency' => $currency,
            'description' => blank($data['description'] ?? null)
                ? null
                : trim((string) $data['description']),
        ]);
    }
}

