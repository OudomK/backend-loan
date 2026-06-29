<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BezhanSalleh\FilamentShield\Support\Utils;
use BezhanSalleh\FilamentShield\Traits\HasShieldFormComponents;
use BezhanSalleh\PluginEssentials\Concerns\Resource as Essentials;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component as Livewire;

use Illuminate\Database\Eloquent\Builder;

class RoleResource extends Resource
{
    use Essentials\BelongsToParent;
    use Essentials\BelongsToTenant;
    use Essentials\HasGlobalSearch;
    use Essentials\HasLabels;
    use Essentials\HasNavigation;
    use HasShieldFormComponents;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function getNavigationSort(): ?int
    {
        return 15;
    }

    /**
     * Get UI feature names grouped for better organization.
     */
    public static function getUiFeatureGroups(): array
    {
        return [
            'Fund Management' => [
                'savings' => 'Borrowing',
                'capital_share' => 'Capital & Share',
                'dividend' => 'Dividend Declaration',
            ],
            'Credit & Operation' => [
                'loan_application' => 'Loan Application',
                'repayment' => 'Repayment (Operation)',
                'loan_operation' => 'Loan Operation',
                'reschedule_refinance' => 'Reschedule & Refinance',
                'customer_management' => 'Customer Management',
                'customer_history' => 'Customer History',
            ],
            'HR & Payroll' => [
                'hr_position' => 'Position (HR)',
                'hr_employee' => 'Employee & Salary (HR)',
                'hr_miscellaneous' => 'Miscellaneous Transactions (HR)',
            ],
            'Financial Management' => [
                'general_expenses' => 'General Expenses',
                'general_revenue' => 'General Revenues',
            ],
            'Reports' => [
                'reports' => 'Reports Dashboard',
                'income_statement' => 'Income Statement',
            ],
            'Menu Visibility' => [
                'credit_menu' => 'Credit Menu',
                'operation_menu' => 'Operation Menu',
            ],
        ];
    }

    /**
     * Get UI CRUD actions with friendly labels.
     */
    protected static function getUiActions(): array
    {
        return [
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'export' => 'Export',
        ];
    }

    public static function getSelectAllFormComponent(): Component
    {
        return Toggle::make('select_all')
            ->onIcon('heroicon-s-shield-check')
            ->offIcon('heroicon-s-shield-exclamation')
            ->label(__('filament-shield::filament-shield.field.select_all.name'))
            ->helperText(fn(): HtmlString => new HtmlString(__('filament-shield::filament-shield.field.select_all.message')))
            ->live()
            ->afterStateUpdated(function (Livewire $livewire, Set $set, Get $get, bool $state): void {
                if (($get('category') ?? 'admin') === 'system_ui') {
                    static::toggleSystemUiViaSelectAll($set, $state);

                    return;
                }

                static::toggleEntitiesViaSelectAll($livewire, $set, $state);
            })
            ->dehydrated(fn(bool $state): bool => $state);
    }

    protected static function toggleSystemUiViaSelectAll(Set $set, bool $state): void
    {
        foreach (static::getUiFeatureGroups() as $groupName => $features) {
            foreach (array_keys($features) as $key) {
                $set("ui_feature_{$key}_show", $state);

                if ($groupName === 'Menu Visibility') {
                    $set("ui_feature_{$key}_actions", []);

                    continue;
                }

                $set(
                    "ui_feature_{$key}_actions",
                    $state ? array_keys(static::getSystemUiActionsForGroup($groupName)) : []
                );
            }
        }
    }

    protected static function getSystemUiActionsForGroup(string $groupName): array
    {
        if ($groupName === 'Reports') {
            return ['export' => 'Export'];
        }

        return static::getUiActions();
    }

    public static function expandUiPermissionAliases(Collection $permissions): Collection
    {
        $expanded = collect($permissions);

        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                continue;
            }

            foreach ([
                'ui:dividend' => 'ui:dividend_declaration',
                'ui:hr_employee' => 'ui:hr_payroll',
            ] as $source => $alias) {
                if ($permission === $source || str_starts_with($permission, "{$source}:")) {
                    $expanded->push($alias . substr($permission, strlen($source)));
                }
            }
        }

        return $expanded->unique()->values();
    }

    public static function isResourceDisabled(string $fqcn): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Filament::auth()->user();

        $map = [
            \App\Filament\Resources\CustomFonts\CustomFontResource::class => 'feature_custom_fonts',
            \App\Filament\Resources\LoanProducts\LoanProductResource::class => 'feature_loan_products',
            \App\Filament\Resources\Translations\TranslationResource::class => 'feature_translations',
            \App\Filament\Resources\ActivityLogs\ActivityLogResource::class => 'feature_activity_logs',
            \App\Filament\Resources\PaymentQrs\PaymentQrResource::class => 'feature_payment_qrs',
            
            \App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource::class => 'feature_expense_categories',
            \App\Filament\Resources\RevenueCategories\RevenueCategoryResource::class => 'feature_revenue_categories',
            \App\Filament\Resources\Expenses\ExpenseResource::class => 'feature_expenses',
            \App\Filament\Resources\Revenues\RevenueResource::class => 'feature_revenues',
            \App\Filament\Resources\MiscellaneousTransactions\MiscellaneousTransactionResource::class => 'feature_misc_transactions',
            
            \App\Filament\Resources\Loans\LoanResource::class => 'feature_loans',
            \App\Filament\Resources\RepaymentTransactions\RepaymentTransactionResource::class => 'feature_repayment_transactions',
            \App\Filament\Resources\OverdueLoans\OverdueLoanResource::class => 'feature_overdue_payments',
            \App\Filament\Resources\LoanModifications\LoanModificationResource::class => 'feature_loan_modifications',
            \App\Filament\Resources\CollateralResource::class => 'feature_collaterals',
            
            \App\Filament\Resources\SavingAccounts\SavingAccountResource::class => 'feature_borrowings',
            \App\Filament\Resources\BorrowingRepayments\BorrowingRepaymentResource::class => 'feature_borrowing_repayments',
            \App\Filament\Resources\CapitalShares\CapitalShareResource::class => 'feature_capital_shares',
            \App\Filament\Resources\CapitalShareTransactions\CapitalShareTransactionResource::class => 'feature_capital_share_transactions',
            \App\Filament\Resources\Dividends\DividendResource::class => 'feature_dividends',
            \App\Filament\Resources\Investors\InvestorResource::class => 'feature_investors',
            
            \App\Filament\Resources\Borrowers\BorrowerResource::class => 'feature_borrowers',
            \App\Filament\Resources\CoBorrowers\CoBorrowerResource::class => 'feature_co_borrowers',
            \App\Filament\Resources\Guarantors\GuarantorResource::class => 'feature_guarantors',
            \App\Filament\Resources\LoanOfficers\LoanOfficerResource::class => 'feature_loan_officers',
            
            \App\Filament\Resources\Employees\EmployeeResource::class => 'feature_employees',
            \App\Filament\Resources\Payrolls\PayrollResource::class => 'feature_payrolls',
            \App\Filament\Resources\Positions\PositionResource::class => 'feature_positions',
        ];

        if (array_key_exists($fqcn, $map)) {
            $key = $map[$fqcn];
            return !\App\Services\FeatureToggle::isAccessible($key, $user);
        }

        return false;
    }

    public static function getResourceEntitiesSchema(): ?array
    {
        return collect(\BezhanSalleh\FilamentShield\Facades\FilamentShield::getResources())
            ->filter(function (array $entity) {
                return !static::isResourceDisabled($entity['resourceFqcn']);
            })
            ->map(function (array $entity): Section {
                $sectionLabel = strval(
                    static::shield()->hasLocalizedPermissionLabels()
                    ? \BezhanSalleh\FilamentShield\Facades\FilamentShield::getLocalizedResourceLabel($entity['resourceFqcn'])
                    : $entity['model']
                );

                return Section::make($sectionLabel)
                    ->description(fn(): HtmlString => new HtmlString('<span style="word-break: break-word;">' . Utils::showModelPath($entity['modelFqcn']) . '</span>'))
                    ->compact()
                    ->schema([
                        static::getCheckBoxListComponentForResource($entity),
                    ])
                    ->columnSpan(static::shield()->getSectionColumnSpan())
                    ->collapsible();
            })
            ->toArray();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament-shield::filament-shield.field.name'))
                                    ->unique(
                                        ignoreRecord: true,
                                        /** @phpstan-ignore-next-line */
                                        modifyRuleUsing: fn(Unique $rule): Unique => Utils::isTenancyEnabled() ? $rule->where(Utils::getTenantModelForeignKey(), Filament::getTenant()?->id) : $rule
                                    )
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('guard_name')
                                    ->label(__('filament-shield::filament-shield.field.guard_name'))
                                    ->default('web')
                                    ->disabled()
                                    ->dehydrated(),

                                Select::make('category')
                                    ->label('Category')
                                    ->options([
                                        'admin' => 'Admin Panel',
                                        'system_ui' => 'System UI (Flutter)',
                                    ])
                                    ->default('admin')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set) {
                                        $set('select_all', false);

                                        if ($state === 'system_ui') {
                                            $set('guard_name', 'web');
                                        }
                                    })
                                    ->helperText('Admin = for admin panel access. System UI = for Flutter app feature access.'),

                                Select::make(config('permission.column_names.team_foreign_key'))
                                    ->label(__('filament-shield::filament-shield.field.team'))
                                    ->placeholder(__('filament-shield::filament-shield.field.team.placeholder'))
                                    /** @phpstan-ignore-next-line */
                                    ->default(Filament::getTenant()?->id)
                                    ->options(fn(): array => in_array(Utils::getTenantModel(), [null, '', '0'], true) ? [] : Utils::getTenantModel()::pluck('name', 'id')->toArray())
                                    ->visible(fn(): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled())
                                    ->dehydrated(fn(): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled()),

                                static::getSelectAllFormComponent(),
                            ])
                            ->columns([
                                'sm' => 2,
                                'lg' => 3,
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                static::getShieldFormComponents()
                    ->visible(fn($get) => ($get('category') ?? 'admin') === 'admin'),

                Section::make('System UI Permissions')
                    ->description('Select which features to show in the Flutter app and what actions are allowed. Organized by feature groups.')
                    ->schema(function () {
                        $sections = [];

                        foreach (static::getUiFeatureGroups() as $groupName => $features) {
                            $featureFields = [];

                            foreach ($features as $key => $label) {
                                $featureFields[] = Fieldset::make($label)
                                    ->schema([
                                        Toggle::make("ui_feature_{$key}_show")
                                            ->label('Show Feature')
                                            ->live()
                                            ->afterStateHydrated(function (Toggle $component, $record) use ($key) {
                                                if (!$record) {
                                                    return;
                                                }

                                                $exists = $record->permissions->pluck('name')
                                                    ->filter(fn($permission) => $permission === "ui:{$key}" || $permission === "ui:{$key}:view")
                                                    ->isNotEmpty();

                                                $component->state($exists);
                                            }),
                                        CheckboxList::make("ui_feature_{$key}_actions")
                                            ->label('Actions')
                                            ->options(fn() => static::getSystemUiActionsForGroup($groupName))
                                            ->columns(2)
                                            ->visible(fn($get) => $get("ui_feature_{$key}_show") && $groupName !== 'Menu Visibility')
                                            ->afterStateHydrated(function (CheckboxList $component, $record) use ($key) {
                                                if (!$record) {
                                                    return;
                                                }

                                                $actions = $record->permissions->pluck('name')
                                                    ->filter(fn($permission) => str_starts_with($permission, "ui:{$key}:") && !str_ends_with($permission, ':view'))
                                                    ->map(fn($permission) => str_replace("ui:{$key}:", '', $permission))
                                                    ->toArray();

                                                $component->state($actions);
                                            }),
                                    ])
                                    ->columnSpan(1);
                            }

                            $sections[] = Section::make($groupName)
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                        'sm' => 2,
                                        'lg' => 3,
                                    ])
                                        ->schema($featureFields),
                                ])
                                ->collapsible();
                        }

                        return $sections;
                    })
                    ->visible(fn($get) => ($get('category') ?? 'admin') === 'system_ui')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Roles')
            ->description('Configure admin panel access and system UI permissions for each role.')
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Medium)
                    ->label(__('filament-shield::filament-shield.column.name'))
                    ->formatStateUsing(fn(string $state): string => Str::headline($state))
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'admin' => 'warning',
                        'system_ui' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'admin' => 'Admin',
                        'system_ui' => 'System UI',
                        default => $state,
                    })
                    ->label('Category'),
                TextColumn::make('guard_name')
                    ->badge()
                    ->color('warning')
                    ->label(__('filament-shield::filament-shield.column.guard_name')),
                TextColumn::make('team.name')
                    ->default('Global')
                    ->badge()
                    ->color(fn(mixed $state): string => str($state)->contains('Global') ? 'gray' : 'primary')
                    ->label(__('filament-shield::filament-shield.column.team'))
                    ->searchable()
                    ->visible(fn(): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled()),
                TextColumn::make('permissions_count')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.permissions'))
                    ->counts('permissions')
                    ->color('primary'),
                TextColumn::make('updated_at')
                    ->label(__('filament-shield::filament-shield.column.updated_at'))
                    ->dateTime(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'admin' => 'Admin',
                        'system_ui' => 'System UI',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Role')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(function () {
                /** @var \App\Models\User|null $user */
                $user = Filament::auth()->user();
                return !$user?->hasRole(Utils::getSuperAdminName());
            }, function (Builder $query) {
                $query->where('name', '!=', Utils::getSuperAdminName());
            });
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function getModel(): string
    {
        return Utils::getRoleModel();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return Utils::getResourceSlug();
    }

    public static function getCluster(): ?string
    {
        return Utils::getResourceCluster();
    }

    public static function getEssentialsPlugin(): ?FilamentShieldPlugin
    {
        return FilamentShieldPlugin::get();
    }
}
