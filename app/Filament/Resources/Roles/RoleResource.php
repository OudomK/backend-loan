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
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

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

    public static function expandUiPermissionAliases(Collection $permissions): Collection
    {
        $expanded = collect($permissions);

        foreach ($permissions as $permission) {
            if (! is_string($permission)) {
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
                                                if (! $record) {
                                                    return;
                                                }

                                                $exists = $record->permissions->pluck('name')
                                                    ->filter(fn($permission) => $permission === "ui:{$key}" || $permission === "ui:{$key}:view")
                                                    ->isNotEmpty();

                                                $component->state($exists);
                                            }),
                                        CheckboxList::make("ui_feature_{$key}_actions")
                                            ->label('Actions')
                                            ->options(function () use ($groupName) {
                                                if ($groupName === 'Reports') {
                                                    return ['export' => 'Export'];
                                                }

                                                return static::getUiActions();
                                            })
                                            ->columns(2)
                                            ->visible(fn($get) => $get("ui_feature_{$key}_show") && $groupName !== 'Menu Visibility')
                                            ->afterStateHydrated(function (CheckboxList $component, $record) use ($key) {
                                                if (! $record) {
                                                    return;
                                                }

                                                $actions = $record->permissions->pluck('name')
                                                    ->filter(fn($permission) => str_starts_with($permission, "ui:{$key}:") && ! str_ends_with($permission, ':view'))
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
