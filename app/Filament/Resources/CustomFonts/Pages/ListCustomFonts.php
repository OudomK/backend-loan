<?php

namespace App\Filament\Resources\CustomFonts\Pages;

use App\Filament\Resources\CustomFonts\CustomFontResource;
use App\Support\AdminFontRegistry;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCustomFonts extends ListRecords
{
    protected static string $resource = CustomFontResource::class;

    public function mount(): void
    {
        AdminFontRegistry::syncCoreFontsToDatabase();

        parent::mount();
    }

    public function getView(): string
    {
        return 'filament.resources.custom-fonts.pages.list-custom-fonts';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Custom Fonts';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Manage imported font families, activation status, and file records.';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getMaxContentWidth(): string|null
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New custom font')
                ->icon('heroicon-m-plus')
                ->color('primary'),
        ];
    }
}
