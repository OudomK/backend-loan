<?php

namespace App\Filament\Resources\Guarantors;

use App\Filament\Resources\Guarantors\Pages\CreateGuarantor;
use App\Filament\Resources\Guarantors\Pages\EditGuarantor;
use App\Filament\Resources\Guarantors\Pages\ListGuarantors;
use App\Filament\Resources\Guarantors\Schemas\GuarantorForm;
use App\Filament\Resources\Guarantors\Tables\GuarantorsTable;
use App\Models\Guarantor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GuarantorResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Client Management';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'phone', 'customer_code', 'id_number'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string|\Illuminate\Contracts\Support\Htmlable
    {
        return "{$record->last_name} {$record->first_name} ({$record->customer_code})";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Phone' => $record->phone,
            'ID' => "{$record->id_type}: {$record->id_number}",
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return GuarantorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuarantorsTable::configure($table);
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
            'index' => ListGuarantors::route('/'),
            'create' => CreateGuarantor::route('/create'),
            'edit' => EditGuarantor::route('/{record}/edit'),
        ];
    }
}

