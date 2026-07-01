<?php

namespace App\Filament\Resources\Revenues;

use App\Filament\Concerns\ChecksFeatureToggle;
use App\Filament\Resources\Revenues\Pages\CreateRevenue;
use App\Filament\Resources\Revenues\Pages\EditRevenue;
use App\Filament\Resources\Revenues\Pages\ListRevenues;
use App\Filament\Resources\Revenues\Pages\ViewRevenue;
use App\Filament\Resources\Revenues\Schemas\RevenueForm;
use App\Filament\Resources\Revenues\Schemas\RevenueInfolist;
use App\Filament\Resources\Revenues\Tables\RevenuesTable;
use App\Models\Revenue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RevenueResource extends Resource
{
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'revenues';
    protected static ?string $model = Revenue::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Financial Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function form(Schema $schema): Schema
    {
        return RevenueForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RevenueInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RevenuesTable::configure($table);
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
            'index' => ListRevenues::route('/'),
            'create' => CreateRevenue::route('/create'),
            'view' => ViewRevenue::route('/{record}'),
            'edit' => EditRevenue::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
