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
            'expense_categories' => FeatureToggle::isEnabled('expense_categories'),
            'revenue_categories' => FeatureToggle::isEnabled('revenue_categories'),
            'general_expenses' => FeatureToggle::isEnabled('general_expenses'),
            'general_revenues' => FeatureToggle::isEnabled('general_revenues'),
            'collateral_management' => FeatureToggle::isEnabled('collateral_management'),
            'hr_payroll' => FeatureToggle::isEnabled('hr_payroll'),
            'capital_share' => FeatureToggle::isEnabled('capital_share'),
            'feature_custom_fonts' => FeatureToggle::isEnabled('feature_custom_fonts'),
            'feature_loan_products' => FeatureToggle::isEnabled('feature_loan_products'),
            'feature_translations' => FeatureToggle::isEnabled('feature_translations'),
            'feature_activity_logs' => FeatureToggle::isEnabled('feature_activity_logs'),
            'feature_payment_qrs' => FeatureToggle::isEnabled('feature_payment_qrs'),
            'feature_expense_categories' => FeatureToggle::isEnabled('feature_expense_categories'),
            'feature_revenue_categories' => FeatureToggle::isEnabled('feature_revenue_categories'),
            'feature_expenses' => FeatureToggle::isEnabled('feature_expenses'),
            'feature_revenues' => FeatureToggle::isEnabled('feature_revenues'),
            'feature_misc_transactions' => FeatureToggle::isEnabled('feature_misc_transactions'),
            'feature_loans' => FeatureToggle::isEnabled('feature_loans'),
            'feature_repayment_transactions' => FeatureToggle::isEnabled('feature_repayment_transactions'),
            'feature_overdue_payments' => FeatureToggle::isEnabled('feature_overdue_payments'),
            'feature_loan_modifications' => FeatureToggle::isEnabled('feature_loan_modifications'),
            'feature_collaterals' => FeatureToggle::isEnabled('feature_collaterals'),
            'feature_borrowings' => FeatureToggle::isEnabled('feature_borrowings'),
            'feature_borrowing_repayments' => FeatureToggle::isEnabled('feature_borrowing_repayments'),
            'feature_capital_shares' => FeatureToggle::isEnabled('feature_capital_shares'),
            'feature_capital_share_transactions' => FeatureToggle::isEnabled('feature_capital_share_transactions'),
            'feature_dividends' => FeatureToggle::isEnabled('feature_dividends'),
            'feature_investors' => FeatureToggle::isEnabled('feature_investors'),
            'feature_borrowers' => FeatureToggle::isEnabled('feature_borrowers'),
            'feature_co_borrowers' => FeatureToggle::isEnabled('feature_co_borrowers'),
            'feature_guarantors' => FeatureToggle::isEnabled('feature_guarantors'),
            'feature_loan_officers' => FeatureToggle::isEnabled('feature_loan_officers'),
            'feature_employees' => FeatureToggle::isEnabled('feature_employees'),
            'feature_payrolls' => FeatureToggle::isEnabled('feature_payrolls'),
            'feature_positions' => FeatureToggle::isEnabled('feature_positions'),
            'feature_reports' => FeatureToggle::isEnabled('feature_reports'),
            'feature_par_analysis' => FeatureToggle::isEnabled('feature_par_analysis'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('General Configuration')
                    ->description('Toggle un-grouped features.')
                    ->schema([
                        Toggle::make('feature_custom_fonts')->label('Enable Custom Fonts')->default(true),
                        Toggle::make('feature_loan_products')->label('Enable Loan Products')->default(true),
                    ])->columns(2),

                Section::make('Administration')
                    ->description('Toggle administrative tools.')
                    ->schema([
                        Toggle::make('feature_translations')->label('Enable Translations')->default(true),
                        Toggle::make('feature_activity_logs')->label('Enable Audit Logs')->default(true),
                        Toggle::make('feature_payment_qrs')->label('Enable Payment QR Codes')->default(true),
                    ])->columns(3),

                Section::make('Financial Management')
                    ->description('Toggle financial categories and transactions.')
                    ->schema([
                        Toggle::make('feature_expense_categories')->label('Enable Expense Categories')->default(true),
                        Toggle::make('feature_revenue_categories')->label('Enable Revenue Categories')->default(true),
                        Toggle::make('feature_expenses')->label('Enable Expenses')->default(true),
                        Toggle::make('feature_revenues')->label('Enable Revenues')->default(true),
                        Toggle::make('feature_misc_transactions')->label('Enable Miscellaneous Transactions')->default(true),
                    ])->columns(3),

                Section::make('Credit Operations')
                    ->description('Toggle loan and credit related features.')
                    ->schema([
                        Toggle::make('feature_loans')->label('Enable Loans')->default(true),
                        Toggle::make('feature_repayment_transactions')->label('Enable Repayment Transactions')->default(true),
                        Toggle::make('feature_overdue_payments')->label('Enable Overdue Payments')->default(true),
                        Toggle::make('feature_loan_modifications')->label('Enable Loan Modifications')->default(true),
                        Toggle::make('feature_collaterals')->label('Enable Collaterals')->default(true),
                    ])->columns(3),

                Section::make('Fund Management')
                    ->description('Toggle shares, investments, and borrowing.')
                    ->schema([
                        Toggle::make('feature_borrowings')->label('Enable Borrowings')->default(true),
                        Toggle::make('feature_borrowing_repayments')->label('Enable Borrowing Repayments')->default(true),
                        Toggle::make('feature_capital_shares')->label('Enable Capital & Shares')->default(true),
                        Toggle::make('feature_capital_share_transactions')->label('Enable Capital Share Transactions')->default(true),
                        Toggle::make('feature_dividends')->label('Enable Dividends')->default(true),
                        Toggle::make('feature_investors')->label('Enable Investors')->default(true),
                    ])->columns(3),

                Section::make('Client Management')
                    ->description('Toggle borrower and guarantor records.')
                    ->schema([
                        Toggle::make('feature_borrowers')->label('Enable Borrowers')->default(true),
                        Toggle::make('feature_co_borrowers')->label('Enable Co Borrowers')->default(true),
                        Toggle::make('feature_guarantors')->label('Enable Guarantors')->default(true),
                        Toggle::make('feature_loan_officers')->label('Enable Loan Officers')->default(true),
                    ])->columns(2),

                Section::make('HR & Payroll')
                    ->description('Toggle human resources features.')
                    ->schema([
                        Toggle::make('feature_employees')->label('Enable Employees')->default(true),
                        Toggle::make('feature_payrolls')->label('Enable Payrolls')->default(true),
                        Toggle::make('feature_positions')->label('Enable Positions')->default(true),
                    ])->columns(3),

                Section::make('Reports')
                    ->description('Toggle report dashboards.')
                    ->schema([
                        Toggle::make('feature_reports')->label('Enable Reports')->default(true),
                        Toggle::make('feature_par_analysis')->label('Enable PAR Analysis')->default(true),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $keys = [
            'feature_custom_fonts', 'feature_loan_products', 'feature_translations', 
            'feature_activity_logs', 'feature_payment_qrs', 'feature_expense_categories', 
            'feature_revenue_categories', 'feature_expenses', 'feature_revenues', 
            'feature_misc_transactions', 'feature_loans', 'feature_repayment_transactions', 
            'feature_overdue_payments', 'feature_loan_modifications', 'feature_collaterals', 
            'feature_borrowings', 'feature_borrowing_repayments', 'feature_capital_shares', 
            'feature_capital_share_transactions', 'feature_dividends', 'feature_investors', 
            'feature_borrowers', 'feature_co_borrowers', 'feature_guarantors', 
            'feature_loan_officers', 'feature_employees', 'feature_payrolls', 
            'feature_positions', 'feature_reports', 'feature_par_analysis'
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
