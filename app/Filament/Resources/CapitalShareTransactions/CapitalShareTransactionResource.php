<?php

namespace App\Filament\Resources\CapitalShareTransactions;

use App\Filament\Resources\CapitalShareTransactions\Pages\ListCapitalShareTransactions;
use App\Filament\Resources\CapitalShareTransactions\Schemas\CapitalShareTransactionForm;
use App\Models\CapitalShare;
use App\Filament\Resources\CapitalShareTransactions\Tables\CapitalShareTransactionsTable;
use App\Models\CapitalShareTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CapitalShareTransactionResource extends Resource
{
    protected static ?string $model = CapitalShareTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Fund Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Capital Share Transactions';

    protected static ?string $modelLabel = 'Capital Share Transaction';

    protected static ?string $pluralModelLabel = 'Capital Share Transactions';

    public static function form(Schema $schema): Schema
    {
        return CapitalShareTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CapitalShareTransactionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['capitalShare.investor', 'capitalShare.lender', 'capitalShare.borrower', 'performedByUser']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCapitalShareTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return true;
    }

    public static function canRestore(Model $record): bool
    {
        return true;
    }

    public static function canForceDelete(Model $record): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeTransactionData(array $data): array
    {
        $shareId = (int) ($data['capital_share_id'] ?? 0);
        $share = CapitalShare::query()->find($shareId);

        if (!$share) {
            throw ValidationException::withMessages([
                'capital_share_id' => 'Capital/share account is required.',
            ]);
        }

        $transactionType = (string) ($data['transaction_type'] ?? '');
        $allowedTypes = static::allowedTransactionTypesForCategory((string) ($share->category ?? ''));

        if (!array_key_exists($transactionType, $allowedTypes)) {
            throw ValidationException::withMessages([
                'transaction_type' => 'This transaction type is not allowed for the selected capital/share account.',
            ]);
        }

        $data['amount'] = round((float) ($data['amount'] ?? 0), 2);
        $data['share_qty'] = (int) ($data['share_qty'] ?? 0);
        $data['performed_by'] = $data['performed_by'] ?? auth()->id();

        if ($data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        if (in_array($transactionType, ['Initial', 'Deposit', 'Withdrawal'], true) && $data['share_qty'] <= 0) {
            throw ValidationException::withMessages([
                'share_qty' => 'Share quantity must be greater than zero for this transaction type.',
            ]);
        }

        if (in_array($transactionType, ['Repayment', 'Dividend'], true) && $data['share_qty'] !== 0) {
            throw ValidationException::withMessages([
                'share_qty' => 'Share quantity must be 0 for repayment and dividend transactions.',
            ]);
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public static function allowedTransactionTypesForCategory(string $category): array
    {
        return match (strtolower(trim($category))) {
            'real capital' => [
                'Initial' => 'Initial',
                'Deposit' => 'Deposit',
                'Withdrawal' => 'Withdrawal',
                'Dividend' => 'Dividend',
            ],
            'loan capital' => [
                'Initial' => 'Initial',
                'Repayment' => 'Repayment',
            ],
            default => [
                'Initial' => 'Initial',
            ],
        };
    }
}
