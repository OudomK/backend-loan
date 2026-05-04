<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SystemUiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'ui:customer_history',
            'ui:customer_history:view',
            'ui:customer_management',
            'ui:customer_management:view',
            'ui:loan_application',
            'ui:loan_application:view',
            'ui:credit_menu',
            'ui:credit_menu:view',
            'ui:operation_menu',
            'ui:operation_menu:view',
            'ui:borrowing',
            'ui:borrowing:view',
            'ui:repayment',
            'ui:repayment:view',
            'ui:reschedule_refinance',
            'ui:reschedule_refinance:view',
            'ui:borrowing_repayment',
            'ui:borrowing_repayment:view',
            'ui:loan_operation',
            'ui:loan_operation:view',
            'ui:savings',
            'ui:savings:view',
            'ui:capital_share',
            'ui:capital_share:view',
            'ui:dividend',
            'ui:dividend:view',
            'ui:dividend_declaration',
            'ui:dividend_declaration:view',
            'ui:hr_position',
            'ui:hr_position:view',
            'ui:hr_employee',
            'ui:hr_employee:view',
            'ui:hr_payroll',
            'ui:hr_payroll:view',
            'ui:hr_miscellaneous',
            'ui:hr_miscellaneous:view',
            'ui:reports',
            'ui:reports:view',
            'ui:income_statement',
            'ui:income_statement:view',
        ];

        foreach ([
            'customer_management',
            'loan_application',
            'borrowing',
            'repayment',
            'reschedule_refinance',
            'borrowing_repayment',
            'savings',
            'capital_share',
            'dividend',
            'dividend_declaration',
            'hr_position',
            'hr_employee',
            'hr_payroll',
            'hr_miscellaneous',
        ] as $feature) {
            foreach (['create', 'edit', 'delete', 'export'] as $action) {
                $permissions[] = "ui:{$feature}:{$action}";
            }
        }

        foreach (['reports', 'income_statement'] as $feature) {
            $permissions[] = "ui:{$feature}:export";
        }

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Created ' . count($permissions) . ' System UI permissions.');
    }
}
