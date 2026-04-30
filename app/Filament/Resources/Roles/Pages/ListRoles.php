<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    public bool $showSuperAdmin = false;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleSuperAdmin')
                ->label(fn () => $this->showSuperAdmin ? 'Hide Super Admin' : 'Show Super Admin')
                ->icon(fn () => $this->showSuperAdmin ? 'heroicon-m-eye-slash' : 'heroicon-m-eye')
                ->color(fn () => $this->showSuperAdmin ? 'danger' : 'gray')
                ->action(function () {
                    $this->showSuperAdmin = ! $this->showSuperAdmin;
                    $this->resetTable();
                })
                ->visible(fn () => auth()->user()?->hasRole('super_admin')),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        // Non-super_admin: always hide super_admin role
        if (! auth()->user()?->hasRole('super_admin')) {
            $query->where('name', '!=', 'super_admin');
        }
        // Super_admin: hide unless toggle is on
        elseif (! $this->showSuperAdmin) {
            $query->where('name', '!=', 'super_admin');
        }

        return $query;
    }

    public function getMaxContentWidth(): string|null
    {
        return 'full';
    }
}
