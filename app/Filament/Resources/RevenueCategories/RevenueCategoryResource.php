<?php

namespace App\Filament\Resources\RevenueCategories;

use App\Filament\Concerns\ChecksFeatureToggle;
use App\Filament\Resources\RevenueCategories\Pages\CreateRevenueCategory;
use App\Filament\Resources\RevenueCategories\Pages\EditRevenueCategory;
use App\Filament\Resources\RevenueCategories\Pages\ListRevenueCategories;
use App\Filament\Resources\RevenueCategories\Schemas\RevenueCategoryForm;
use App\Filament\Resources\RevenueCategories\Tables\RevenueCategoriesTable;
use App\Models\RevenueCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RevenueCategoryResource extends Resource
{
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'revenue_categories';
    protected static ?string $model = RevenueCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Financial Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Revenue Categories';
    }

    public static function getModelLabel(): string
    {
        return 'Revenue Category';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Revenue Categories';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'group_name', 'description'];
    }

    public static function form(Schema $schema): Schema
    {
        return RevenueCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RevenueCategoriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
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
            'index' => ListRevenueCategories::route('/'),
            'create' => CreateRevenueCategory::route('/create'),
            'edit' => EditRevenueCategory::route('/{record}/edit'),
        ];
    }
}
