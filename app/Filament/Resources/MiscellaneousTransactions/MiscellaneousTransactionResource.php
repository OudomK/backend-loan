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

class MiscellaneousTransactionResource extends Resource
{
    protected static ?string $model = MiscellaneousTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'description';

    public static function getGloballySearchableAttributes(): array
    {
        return ['description', 'category', 'amount'];
    }

    public static function form(Schema $schema): Schema
    {
        return MiscellaneousTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MiscellaneousTransactionsTable::configure($table);
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
}
