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
        $user = \Filament\Facades\Filament::auth()->user();
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
            'savings' => FeatureToggle::isEnabled('savings'),
            'payment_qrs' => FeatureToggle::isEnabled('payment_qrs'),
            'custom_fonts' => FeatureToggle::isEnabled('custom_fonts'),
            'activity_logs' => FeatureToggle::isEnabled('activity_logs'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('System Configuration')
                    ->description('Toggle core configuration features.')
                    ->schema([
                        Toggle::make('payment_qrs')
                            ->label('Enable Payment QRs')
                            ->helperText('Manage QR codes for accepting payments.')
                            ->default(true),
                        Toggle::make('custom_fonts')
                            ->label('Enable Custom Fonts')
                            ->helperText('Allow admins to import and manage custom fonts.')
                            ->default(true),
                        Toggle::make('activity_logs')
                            ->label('Enable Activity Logs')
                            ->helperText('Track user actions and system changes.')
                            ->default(true),
                    ])->columns(2),

                Section::make('Financial Management Module')
                    ->description('Toggle features related to financial categories and institutional expenses.')
                    ->schema([
                        Toggle::make('expense_categories')
                            ->label('Enable Expense Categories')
                            ->helperText('Manage categories for system expenses.')
                            ->default(true),
                        Toggle::make('revenue_categories')
                            ->label('Enable Revenue Categories')
                            ->helperText('Manage categories for system revenues.')
                            ->default(true),
                        Toggle::make('general_expenses')
                            ->label('Enable General Expenses')
                            ->helperText('Manage large/formal institution expenses separate from miscellaneous transactions.')
                            ->default(true),
                        Toggle::make('general_revenues')
                            ->label('Enable General Revenues')
                            ->helperText('Manage large/formal institution revenues separate from miscellaneous transactions.')
                            ->default(true),
                        Toggle::make('collateral_management')
                            ->label('Enable Collateral Management')
                            ->helperText('Dedicated module for tracking and managing loan collateral.')
                            ->default(true),
                    ])->columns(2),

                Section::make('HR & Payroll Module')
                    ->description('Toggle the entire Human Resources and Payroll system.')
                    ->schema([
                        Toggle::make('hr_payroll')
                            ->label('Enable HR & Payroll')
                            ->helperText('Includes Employees, Positions, Payrolls, Leave Requests, and Misc Transactions.')
                            ->default(true),
                    ])->columns(1),

                Section::make('Fund Management Module')
                    ->description('Toggle features related to funding, shares, and savings.')
                    ->schema([
                        Toggle::make('capital_share')
                            ->label('Enable Capital & Share')
                            ->helperText('Includes Investors, Capital Shares, and Share Transactions.')
                            ->default(true),
                        Toggle::make('savings')
                            ->label('Enable Savings & Borrowing')
                            ->helperText('Includes Saving Accounts and Borrowing Repayments.')
                            ->default(true),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        FeatureToggle::set('expense_categories', $data['expense_categories']);
        FeatureToggle::set('revenue_categories', $data['revenue_categories']);
        FeatureToggle::set('general_expenses', $data['general_expenses']);
        FeatureToggle::set('general_revenues', $data['general_revenues']);
        FeatureToggle::set('collateral_management', $data['collateral_management']);
        FeatureToggle::set('hr_payroll', $data['hr_payroll']);
        FeatureToggle::set('capital_share', $data['capital_share']);
        FeatureToggle::set('savings', $data['savings']);
        FeatureToggle::set('payment_qrs', $data['payment_qrs']);
        FeatureToggle::set('custom_fonts', $data['custom_fonts']);
        FeatureToggle::set('activity_logs', $data['activity_logs']);
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
