<?php

namespace App\Filament\Pages;

use App\Services\FeatureToggle;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminControl extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.admin-control';

    public function getHeading(): string
    {
        return '';
    }

    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Filament::auth()->user();
        return $user?->hasRole(Utils::getSuperAdminName()) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'custom_fonts' => FeatureToggle::isEnabled('custom_fonts'),
            'loan_products' => FeatureToggle::isEnabled('loan_products'),
            'id_types' => FeatureToggle::isEnabled('id_types'),
            'relationships' => FeatureToggle::isEnabled('relationships'),
            'translations' => FeatureToggle::isEnabled('translations'),
            'activity_logs' => FeatureToggle::isEnabled('activity_logs'),
            'payment_qrs' => FeatureToggle::isEnabled('payment_qrs'),
            'payment_methods' => FeatureToggle::isEnabled('payment_methods'),
            'expense_categories' => FeatureToggle::isEnabled('expense_categories'),
            'revenue_categories' => FeatureToggle::isEnabled('revenue_categories'),
            'expenses' => FeatureToggle::isEnabled('expenses'),
            'revenues' => FeatureToggle::isEnabled('revenues'),
            'misc_transactions' => FeatureToggle::isEnabled('misc_transactions'),
            'loans' => FeatureToggle::isEnabled('loans'),
            'repayment_transactions' => FeatureToggle::isEnabled('repayment_transactions'),
            'overdue_payments' => FeatureToggle::isEnabled('overdue_payments'),
            'loan_modifications' => FeatureToggle::isEnabled('loan_modifications'),
            'collaterals' => FeatureToggle::isEnabled('collaterals'),
            'borrowings' => FeatureToggle::isEnabled('borrowings'),
            'borrowing_repayments' => FeatureToggle::isEnabled('borrowing_repayments'),
            'capital_shares' => FeatureToggle::isEnabled('capital_shares'),
            'capital_share_transactions' => FeatureToggle::isEnabled('capital_share_transactions'),
            'dividends' => FeatureToggle::isEnabled('dividends'),
            'investors' => FeatureToggle::isEnabled('investors'),
            'borrowers' => FeatureToggle::isEnabled('borrowers'),
            'co_borrowers' => FeatureToggle::isEnabled('co_borrowers'),
            'guarantors' => FeatureToggle::isEnabled('guarantors'),
            'loan_officers' => FeatureToggle::isEnabled('loan_officers'),
            'employees' => FeatureToggle::isEnabled('employees'),
            'payrolls' => FeatureToggle::isEnabled('payrolls'),
            'positions' => FeatureToggle::isEnabled('positions'),
            'reports' => FeatureToggle::isEnabled('reports'),
            'par_analysis' => FeatureToggle::isEnabled('par_analysis'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Feature Controls')
                    ->description('Toggle system features on or off.')
                    ->schema([
                        Toggle::make('custom_fonts')->label('Enable Custom Fonts')->default(true),
                        Toggle::make('loan_products')->label('Enable Loan Products')->default(true),
                        Toggle::make('id_types')->label('Enable ID Types')->default(true),
                        Toggle::make('relationships')->label('Enable Relationships')->default(true),
                        Toggle::make('translations')->label('Enable Translations')->default(true),
                        Toggle::make('activity_logs')->label('Enable Audit Logs')->default(true),
                        Toggle::make('payment_qrs')->label('Enable Payment QR Codes')->default(true),
                        Toggle::make('payment_methods')->label('Enable Payment Methods')->default(true),
                        Toggle::make('expense_categories')->label('Enable Expense Categories')->default(true),
                        Toggle::make('revenue_categories')->label('Enable Revenue Categories')->default(true),
                        Toggle::make('expenses')->label('Enable Expenses')->default(true),
                        Toggle::make('revenues')->label('Enable Revenues')->default(true),
                        Toggle::make('misc_transactions')->label('Enable Miscellaneous Transactions')->default(true),
                        Toggle::make('loans')->label('Enable Loans')->default(true),
                        Toggle::make('repayment_transactions')->label('Enable Repayment Transactions')->default(true),
                        Toggle::make('overdue_payments')->label('Enable Overdue Payments')->default(true),
                        Toggle::make('loan_modifications')->label('Enable Loan Modifications')->default(true),
                        Toggle::make('collaterals')->label('Enable Collaterals')->default(true),
                        Toggle::make('borrowings')->label('Enable Borrowings')->default(true),
                        Toggle::make('borrowing_repayments')->label('Enable Borrowing Repayments')->default(true),
                        Toggle::make('capital_shares')->label('Enable Capital & Shares')->default(true),
                        Toggle::make('capital_share_transactions')->label('Enable Capital Share Transactions')->default(true),
                        Toggle::make('dividends')->label('Enable Dividends')->default(true),
                        Toggle::make('investors')->label('Enable Investors')->default(true),
                        Toggle::make('borrowers')->label('Enable Borrowers')->default(true),
                        Toggle::make('co_borrowers')->label('Enable Co Borrowers')->default(true),
                        Toggle::make('guarantors')->label('Enable Guarantors')->default(true),
                        Toggle::make('loan_officers')->label('Enable Loan Officers')->default(true),
                        Toggle::make('employees')->label('Enable Employees')->default(true),
                        Toggle::make('payrolls')->label('Enable Payrolls')->default(true),
                        Toggle::make('positions')->label('Enable Positions')->default(true),
                        Toggle::make('reports')->label('Enable Reports')->default(true),
                        Toggle::make('par_analysis')->label('Enable PAR Analysis')->default(true),
                    ])->columns(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $keys = [
            'custom_fonts',
            'loan_products',
            'id_types',
            'relationships',
            'translations',
            'activity_logs',
            'payment_qrs',
            'payment_methods',
            'expense_categories',
            'revenue_categories',
            'expenses',
            'revenues',
            'misc_transactions',
            'loans',
            'repayment_transactions',
            'overdue_payments',
            'loan_modifications',
            'collaterals',
            'borrowings',
            'borrowing_repayments',
            'capital_shares',
            'capital_share_transactions',
            'dividends',
            'investors',
            'borrowers',
            'co_borrowers',
            'guarantors',
            'loan_officers',
            'employees',
            'payrolls',
            'positions',
            'reports',
            'par_analysis'
        ];

        foreach ($keys as $key) {
            FeatureToggle::set($key, $data[$key] ?? true);
        }
        $this->ensureAdminRoleHasPermissions();

        Notification::make()
            ->title('Feature toggles updated successfully')
            ->success()
            ->send();
    }

    private function ensureAdminRoleHasPermissions(): void
    {
        /** @var Role|null $adminRole */
        $adminRole = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', Filament::getCurrentPanel()?->getAuthGuard() ?? 'web')
            ->first();

        if (!$adminRole) {
            return;
        }

        $permissionModel = Utils::getPermissionModel();
        $permissions = $permissionModel::query()->get();

        foreach ($permissions as $permission) {
            if (!$adminRole->hasPermissionTo($permission)) {
                $adminRole->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
