<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Role;

$roles = Role::with('permissions')->get();

foreach ($roles as $role) {
    echo "Role: {$role->name} (ID: {$role->id}, Category: {$role->category}, Guard: {$role->guard_name})\n";
    echo "Permissions:\n";
    foreach ($role->permissions as $perm) {
        echo "  - {$perm->name} (Guard: {$perm->guard_name})\n";
    }
    echo "------------------------------------------\n";
}
