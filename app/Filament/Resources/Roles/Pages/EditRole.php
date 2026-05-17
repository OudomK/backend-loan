<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class EditRole extends EditRecord
{
    public Collection $permissions;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Populate the ui_feature_* fields from the record's existing permissions.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Collect permissions from Shield form components and ui_feature_* fields
        $excludeKeys = ['name', 'guard_name', 'select_all', 'category', 'ui_permissions', Utils::getTenantModelForeignKey()];

        $this->permissions = collect($data)
            ->filter(fn(mixed $permission, string $key): bool => !str_starts_with($key, 'ui_feature_') && !in_array($key, $excludeKeys))
            ->values()
            ->flatten()
            ->unique();

        // Also add ui_feature_* permissions if present (for system_ui category)
        foreach ($data as $key => $value) {
            // Check for Show Feature toggle
            if (str_starts_with($key, 'ui_feature_') && str_ends_with($key, '_show') && $value === true) {
                $feature = str_replace(['ui_feature_', '_show'], '', $key);
                $this->permissions->push("ui:{$feature}:view");

                // Add actions if set
                $actionsKey = "ui_feature_{$feature}_actions";
                if (isset($data[$actionsKey]) && is_array($data[$actionsKey])) {
                    foreach ($data[$actionsKey] as $action) {
                        $this->permissions->push("ui:{$feature}:{$action}");
                    }
                }
            }
        }

        // Preserve existing permissions for disabled features so they aren't lost on save
        $existingPermissions = $this->record->permissions->pluck('name');
        $allResources = \BezhanSalleh\FilamentShield\Facades\FilamentShield::getResources();
        
        foreach ($allResources as $entity) {
            if (RoleResource::isResourceDisabled($entity['resourceFqcn'])) {
                // Feature is disabled, so its checkboxes weren't in the form.
                // Re-inject its existing permissions so syncPermissions doesn't delete them.
                foreach ($entity['permissions'] ?? [] as $permissionName) {
                    if ($existingPermissions->contains($permissionName)) {
                        $this->permissions->push($permissionName);
                    }
                }
            }
        }

        $this->permissions = RoleResource::expandUiPermissionAliases($this->permissions);

        $keepKeys = ['name', 'guard_name', 'category'];
        if (Utils::isTenancyEnabled() && Arr::has($data, Utils::getTenantModelForeignKey()) && filled($data[Utils::getTenantModelForeignKey()])) {
            $keepKeys[] = Utils::getTenantModelForeignKey();
        }

        return Arr::only($data, $keepKeys);
    }

    protected function afterSave(): void
    {
        $permissionModels = collect();
        $this->permissions->each(function (string $permission) use ($permissionModels): void {
            $permissionModels->push(Utils::getPermissionModel()::firstOrCreate([
                'name' => $permission,
                'guard_name' => $this->data['guard_name'],
            ]));
        });

        // @phpstan-ignore-next-line
        $this->record->syncPermissions($permissionModels);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
