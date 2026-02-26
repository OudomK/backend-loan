<?php

namespace App\Filament\Resources\CapitalShares;

use App\Filament\Resources\CapitalShares\Pages\CreateCapitalShare;
use App\Filament\Resources\CapitalShares\Pages\EditCapitalShare;
use App\Filament\Resources\CapitalShares\Pages\ListCapitalShares;
use App\Filament\Resources\CapitalShares\Schemas\CapitalShareForm;
use App\Filament\Resources\CapitalShares\Tables\CapitalSharesTable;
use App\Models\CapitalShare;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class CapitalShareResource extends Resource
{
    protected static ?string $model = CapitalShare::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'account_no';

    public static function getGloballySearchableAttributes(): array
    {
        return ['account_no', 'lender.name'];
    }

    public static function form(Schema $schema): Schema
    {
        return CapitalShareForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CapitalSharesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['lender']);
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
            'index' => ListCapitalShares::route('/'),
            'create' => CreateCapitalShare::route('/create'),
            'edit' => EditCapitalShare::route('/{record}/edit'),
        ];
    }
}
