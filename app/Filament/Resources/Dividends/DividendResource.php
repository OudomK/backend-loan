<?php

namespace App\Filament\Resources\Dividends;

use App\Filament\Resources\Dividends\Pages\CreateDividend;
use App\Filament\Resources\Dividends\Pages\EditDividend;
use App\Filament\Resources\Dividends\Pages\ListDividends;
use App\Filament\Resources\Dividends\Schemas\DividendForm;
use App\Filament\Resources\Dividends\Tables\DividendsTable;
use App\Models\Dividend;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DividendResource extends Resource
{
    protected static ?string $model = Dividend::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static string|\UnitEnum|null $navigationGroup = 'Fund Management';
    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return DividendForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DividendsTable::configure($table);
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
            'index' => ListDividends::route('/'),
            'create' => CreateDividend::route('/create'),
            'edit' => EditDividend::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
