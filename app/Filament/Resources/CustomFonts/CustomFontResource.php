<?php

namespace App\Filament\Resources\CustomFonts;

use App\Filament\Resources\CustomFonts\Pages\CreateCustomFont;
use App\Filament\Resources\CustomFonts\Pages\EditCustomFont;
use App\Filament\Resources\CustomFonts\Pages\ListCustomFonts;
use App\Filament\Resources\CustomFonts\Schemas\CustomFontForm;
use App\Filament\Resources\CustomFonts\Tables\CustomFontsTable;
use App\Models\CustomFont;
use App\Services\FeatureToggle;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomFontResource extends Resource
{
    protected static ?string $model = CustomFont::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool
    {
        return FeatureToggle::isAccessible('custom_fonts', Filament::auth()->user());
    }

    public static function canAccess(): bool
    {
        return FeatureToggle::isAccessible('custom_fonts', Filament::auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return CustomFontForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomFontsTable::configure($table);
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
            'index' => ListCustomFonts::route('/'),
            'create' => CreateCustomFont::route('/create'),
            'edit' => EditCustomFont::route('/{record}/edit'),
        ];
    }
}
