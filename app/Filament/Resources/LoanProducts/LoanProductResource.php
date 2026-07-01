<?php

namespace App\Filament\Resources\LoanProducts;

use App\Filament\Concerns\ChecksFeatureToggle;
use App\Filament\Resources\LoanProducts\Pages\CreateLoanProduct;
use App\Filament\Resources\LoanProducts\Pages\EditLoanProduct;
use App\Filament\Resources\LoanProducts\Pages\ListLoanProducts;
use App\Filament\Resources\LoanProducts\Schemas\LoanProductForm;
use App\Filament\Resources\LoanProducts\Tables\LoanProductsTable;
use App\Models\LoanProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LoanProductResource extends Resource
{
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'loan_products';
    protected static ?string $model = LoanProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LoanProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoanProductsTable::configure($table);
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
            'index' => ListLoanProducts::route('/'),
            'create' => CreateLoanProduct::route('/create'),
            'edit' => EditLoanProduct::route('/{record}/edit'),
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
