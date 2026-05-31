<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        /** @var \App\Models\User|null $user */
        $user = Filament::auth()->user();
        $superAdminRole = Utils::getSuperAdminName();

        if (! $user?->hasRole($superAdminRole) && $this->record->hasRole($superAdminRole)) {
            abort(403);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(function (): bool {
                    /** @var \App\Models\User|null $user */
                    $user = Filament::auth()->user();
                    return $user?->hasRole(Utils::getSuperAdminName()) ?? false;
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
