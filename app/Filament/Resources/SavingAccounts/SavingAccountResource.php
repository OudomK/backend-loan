<?php

namespace App\Filament\Resources\SavingAccounts;

use App\Filament\Resources\SavingAccounts\Pages\CreateSavingAccount;
use App\Filament\Resources\SavingAccounts\Pages\EditSavingAccount;
use App\Filament\Resources\SavingAccounts\Pages\ListSavingAccounts;
use App\Filament\Resources\SavingAccounts\Schemas\SavingAccountForm;
use App\Filament\Resources\SavingAccounts\Tables\SavingAccountsTable;
use App\Models\SavingAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class SavingAccountResource extends Resource
{
    protected static ?string $model = SavingAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'account_number';
    protected static ?string $navigationLabel = 'Borrowings';
    protected static ?string $modelLabel = 'Borrowing';
    protected static ?string $pluralModelLabel = 'Borrowings';

    public static function getGloballySearchableAttributes(): array
    {
        return ['account_number', 'borrower.name'];
    }

    public static function form(Schema $schema): Schema
    {
        return SavingAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SavingAccountsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['borrower']);
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
            'index' => ListSavingAccounts::route('/'),
            'create' => CreateSavingAccount::route('/create'),
            'edit' => EditSavingAccount::route('/{record}/edit'),
        ];
    }
}
