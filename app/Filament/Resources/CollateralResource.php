<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Collaterals\Pages\CreateCollateral;
use App\Filament\Resources\Collaterals\Pages\EditCollateral;
use App\Filament\Resources\Collaterals\Pages\ListCollaterals;
use App\Filament\Resources\Collaterals\Pages\ViewCollateral;
use App\Filament\Resources\Collaterals\Schemas\CollateralForm;
use App\Filament\Resources\Collaterals\Tables\CollateralsTable;
use App\Models\Collateral;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CollateralResource extends Resource
{
    protected static ?string $model = Collateral::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Credit Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'certificate_number';

    public static function form(Schema $schema): Schema
    {
        return CollateralForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CollateralsTable::configure($table);
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
            'index' => ListCollaterals::route('/'),
            'create' => CreateCollateral::route('/create'),
            'view' => ViewCollateral::route('/{record}'),
            'edit' => EditCollateral::route('/{record}/edit'),
        ];
    }
}
