<?php

namespace App\Filament\Resources\RepaymentTransactions;

use App\Filament\Concerns\ChecksFeatureToggle;
use App\Filament\Resources\RepaymentTransactions\Pages\CreateRepaymentTransaction;
use App\Filament\Resources\RepaymentTransactions\Pages\EditRepaymentTransaction;
use App\Filament\Resources\RepaymentTransactions\Pages\ListRepaymentTransactions;
use App\Filament\Resources\RepaymentTransactions\Schemas\RepaymentTransactionForm;
use App\Filament\Resources\RepaymentTransactions\Tables\RepaymentTransactionsTable;
use App\Models\RepaymentTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class RepaymentTransactionResource extends Resource
{
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'repayment_transactions';
    protected static ?string $model = RepaymentTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Credit Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return RepaymentTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RepaymentTransactionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['loan.borrower', 'collector']);
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
            'index' => ListRepaymentTransactions::route('/'),
            'create' => CreateRepaymentTransaction::route('/create'),
            'edit' => EditRepaymentTransaction::route('/{record}/edit'),
        ];
    }
}

