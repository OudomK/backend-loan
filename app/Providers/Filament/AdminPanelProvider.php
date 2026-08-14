<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Models\Setting;
use App\Support\AdminFontRegistry;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->favicon('data:,')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->spa()
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->defaultThemeMode(ThemeMode::Dark)
            ->font(
                family: 'Kantumruy Pro',
                provider: LocalFontProvider::class,
                preload: [],
            )
            ->colors([
                'primary' => Color::Teal,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn(): HtmlString => new HtmlString($this->renderAdminFontStyle()),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): HtmlString => new HtmlString(<<<'HTML'
                    <style>
                        :root {
                            --sidebar-width: 240px !important;
                        }
                    </style>
                HTML),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\DisbursementLineChart::class,
                \App\Filament\Widgets\RepaymentsBarChart::class,
                \App\Filament\Widgets\MonthlyPerformanceChart::class,
                \App\Filament\Widgets\RecentActivityTable::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                \App\Http\Middleware\CheckAdminSecret::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\SingleSession::class,
            ], isPersistent: true);
    }

    private function renderAdminFontStyle(): string
    {
        $fontFaceCss = '';
        try {
            $activeCustomFonts = Cache::remember('filament.admin.custom_fonts', 60 * 60, function () {
                if (Schema::hasTable('custom_fonts')) {
                    return \App\Models\CustomFont::query()
                        ->where('is_active', true)
                        ->when(
                            Schema::hasColumn('custom_fonts', 'is_system'),
                            fn ($query) => $query->where('is_system', false),
                        )
                        ->get();
                }
                return collect();
            });

            foreach ($activeCustomFonts as $font) {
                $url = asset('storage/' . $font->file_path);
                $format = str_ends_with(strtolower($font->file_path), '.otf') ? 'opentype' : 'truetype';
                $fontFaceCss .= "
                @font-face {
                    font-family: '{$font->name}';
                    src: url('{$url}') format('{$format}');
                    font-weight: normal;
                    font-style: normal;
                    font-display: swap;
                }
                ";
            }
        } catch (\Throwable $e) {}

        return sprintf(
            '%s
            <style id="admin-font-family-override">
                :root, html.fi, body.fi-body {
                    --font-family: %s !important;
                    --fi-font-family: %s !important;
                    font-family: var(--font-family) !important;
                }
                .fi-body,
                .fi-body :where(.fi-page, .fi-header, .fi-sidebar, .fi-ta, .fi-fo, .fi-btn, .fi-input, .fi-select-input, .fi-dropdown, .fi-modal, .fi-section, .fi-tabs, .fi-breadcrumbs) {
                    font-family: var(--font-family) !important;
                }
            </style>',
            $fontFaceCss ? "<style>{$fontFaceCss}</style>" : '',
            $this->resolveAdminFontStack(),
            $this->resolveAdminFontStack(),
        );
    }

    private function resolveAdminFontStack(): string
    {
        try {
            return Cache::remember('filament.admin.font_stack', 60 * 60, function () {
                if (!Schema::hasTable('settings')) {
                    return AdminFontRegistry::cssStack(null);
                }

                $selected = Setting::query()
                    ->where('key', 'admin_font_family')
                    ->value('value');

                return AdminFontRegistry::cssStack(is_string($selected) ? $selected : null);
            });
        } catch (\Throwable) {
            return AdminFontRegistry::cssStack(null);
        }
    }
}
