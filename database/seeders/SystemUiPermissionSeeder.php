<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SystemUiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'ui:customer_history',
            'ui:customer_management',
            'ui:loan_application',
            'ui:credit_menu',
            'ui:operation_menu',
            'ui:savings',
            'ui:capital_share',
            'ui:dividend',
            'ui:hr_position',
            'ui:hr_employee',
            'ui:hr_miscellaneous',
            'ui:reports',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        $this->command->info('Created ' . count($permissions) . ' System UI permissions.');
    }
}
