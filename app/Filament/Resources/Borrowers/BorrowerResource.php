<?php

namespace App\Filament\Resources\Borrowers;

use App\Filament\Resources\Borrowers\Pages\CreateBorrower;
use App\Filament\Resources\Borrowers\Pages\EditBorrower;
use App\Filament\Resources\Borrowers\Pages\ListBorrowers;
use App\Filament\Resources\Borrowers\Schemas\BorrowerForm;
use App\Filament\Resources\Borrowers\Tables\BorrowersTable;
use App\Models\Borrower;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BorrowerResource extends Resource
{
    protected static ?string $model = Borrower::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Client Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'phone', 'customer_code'];
    }

    public static function form(Schema $schema): Schema
    {
        return BorrowerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BorrowersTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
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
            'index' => ListBorrowers::route('/'),
            'create' => CreateBorrower::route('/create'),
            'edit' => EditBorrower::route('/{record}/edit'),
        ];
    }
}

