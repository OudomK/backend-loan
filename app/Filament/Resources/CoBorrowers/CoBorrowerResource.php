<?php

namespace App\Filament\Resources\CoBorrowers;

use App\Filament\Resources\CoBorrowers\Pages\CreateCoBorrower;
use App\Filament\Resources\CoBorrowers\Pages\EditCoBorrower;
use App\Filament\Resources\CoBorrowers\Pages\ListCoBorrowers;
use App\Filament\Resources\CoBorrowers\Schemas\CoBorrowerForm;
use App\Filament\Resources\CoBorrowers\Tables\CoBorrowersTable;
use App\Models\CoBorrower;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CoBorrowerResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Client Management';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'customer_code';

    public static function form(Schema $schema): Schema
    {
        return CoBorrowerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoBorrowersTable::configure($table);
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
            'index' => ListCoBorrowers::route('/'),
            'create' => CreateCoBorrower::route('/create'),
            'edit' => EditCoBorrower::route('/{record}/edit'),
        ];
    }
}

