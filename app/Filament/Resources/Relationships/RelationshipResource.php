<?php

namespace App\Filament\Resources\Relationships;

use App\Filament\Resources\Relationships\Pages\CreateRelationship;
use App\Filament\Resources\Relationships\Pages\EditRelationship;
use App\Filament\Resources\Relationships\Pages\ListRelationships;
use App\Filament\Resources\Relationships\Schemas\RelationshipForm;
use App\Filament\Resources\Relationships\Tables\RelationshipsTable;
use App\Models\Relationship;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Concerns\ChecksFeatureToggle;

class RelationshipResource extends Resource
{
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'relationships';

    protected static ?string $model = Relationship::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';



    public static function form(Schema $schema): Schema
    {
        return RelationshipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RelationshipsTable::configure($table);
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
            'index' => ListRelationships::route('/'),
            'create' => CreateRelationship::route('/create'),
            'edit' => EditRelationship::route('/{record}/edit'),
        ];
    }
}
