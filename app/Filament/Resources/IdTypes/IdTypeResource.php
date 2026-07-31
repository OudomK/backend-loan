<?php

namespace App\Filament\Resources\IdTypes;

use App\Filament\Resources\IdTypes\Pages\CreateIdType;
use App\Filament\Resources\IdTypes\Pages\EditIdType;
use App\Filament\Resources\IdTypes\Pages\ListIdTypes;
use App\Filament\Resources\IdTypes\Schemas\IdTypeForm;
use App\Filament\Resources\IdTypes\Tables\IdTypesTable;
use App\Models\IdType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Concerns\ChecksFeatureToggle;

class IdTypeResource extends Resource
{
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'id_types';

    protected static ?string $model = IdType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return IdTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IdTypesTable::configure($table);
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
            'index' => ListIdTypes::route('/'),
            'create' => CreateIdType::route('/create'),
            'edit' => EditIdType::route('/{record}/edit'),
        ];
    }
}
