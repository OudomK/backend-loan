<?php

namespace App\Filament\Resources\CapitalShareTransactions;

use App\Filament\Resources\CapitalShareTransactions\Pages\CreateCapitalShareTransaction;
use App\Filament\Resources\CapitalShareTransactions\Pages\EditCapitalShareTransaction;
use App\Filament\Resources\CapitalShareTransactions\Pages\ListCapitalShareTransactions;
use App\Filament\Resources\CapitalShareTransactions\Schemas\CapitalShareTransactionForm;
use App\Filament\Resources\CapitalShareTransactions\Tables\CapitalShareTransactionsTable;
use App\Models\CapitalShareTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            'create' => CreateCapitalShareTransaction::route('/create'),
            'edit' => EditCapitalShareTransaction::route('/{record}/edit'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeTransactionData(array $data): array
    {
        $data['amount'] = round((float) ($data['amount'] ?? 0), 2);
        $data['share_qty'] = (int) ($data['share_qty'] ?? 0);
        $data['performed_by'] = $data['performed_by'] ?? auth()->id();

        return $data;
    }
}
