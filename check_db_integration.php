<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "--- Database Integration Check ---\n";

// 1. Check roles table
if (Schema::hasTable('roles')) {
    echo "✅ Table 'roles' exists.\n";
    if (Schema::hasColumn('roles', 'category')) {
        echo "✅ Column 'category' exists in 'roles'.\n";
    } else {
        echo "❌ Column 'category' is MISSING in 'roles'.\n";
    }
} else {
    echo "❌ Table 'roles' is MISSING.\n";
}

// 2. Check Role entries with category 'ui'
try {
    $uiRoles = DB::table('roles')->where('category', 'ui')->get();
    echo "Found " . $uiRoles->count() . " UI roles.\n";
    foreach ($uiRoles as $role) {
        echo " - Role: " . $role->name . " (ID: " . $role->id . ")\n";
    }
} catch (\Exception $e) {
    echo "❌ Error checking UI roles: " . $e->getMessage() . "\n";
}

// 3. Check UI permissions
try {
    $uiPermissions = DB::table('permissions')->where('name', 'like', 'ui:%')->get();
    echo "Found " . $uiPermissions->count() . " 'ui:' prefixed permissions.\n";
    if ($uiPermissions->count() > 0) {
        foreach ($uiPermissions->take(5) as $perm) {
            echo " - Permission: " . $perm->name . "\n";
        }
        if ($uiPermissions->count() > 5)
            echo "   ... and more\n";
    }
} catch (\Exception $e) {
    echo "❌ Error checking UI permissions: " . $e->getMessage() . "\n";
}

// 4. Check if any UI role has UI permissions
try {
    $roleHasPermissions = DB::table('role_has_permissions')
        ->join('roles', 'role_has_permissions.role_id', '=', 'roles.id')
        ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
        ->where('roles.category', 'ui')
        ->select('roles.name as role_name', 'permissions.name as perm_name')
        ->limit(10)
        ->get();

    echo "Found " . $roleHasPermissions->count() . " mappings between UI roles and permissions (sampled).\n";
    foreach ($roleHasPermissions as $mapping) {
        echo " - " . $mapping->role_name . " has " . $mapping->perm_name . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error checking role-permission mappings: " . $e->getMessage() . "\n";
}

echo "--- End of Check ---\n";
