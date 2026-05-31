<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissionModel = config('permission.models.permission');
        $roleModel = config('permission.models.role');

        if (! is_string($permissionModel) || ! class_exists($permissionModel)) {
            return;
        }

        $permissionNames = collect(['general_expenses', 'general_revenue'])
            ->flatMap(fn (string $feature): array => [
                "ui:{$feature}:view",
                "ui:{$feature}:create",
                "ui:{$feature}:edit",
                "ui:{$feature}:delete",
                "ui:{$feature}:export",
            ]);

        $permissions = $permissionNames
            ->map(fn (string $name) => $permissionModel::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        if (is_string($roleModel) && class_exists($roleModel)) {
            $roleModel::query()
                ->whereIn('name', ['admin', 'super_admin'])
                ->where('guard_name', 'web')
                ->get()
                ->each(fn ($role) => $role->givePermissionTo($permissions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
