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
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandLogo(asset('images/logo.jpg'))
            ->darkModeBrandLogo(asset('images/dark_logo.png'))
            ->brandLogoHeight('4rem')
            ->favicon(asset('images/logo.jpg'))
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
                fn(): HtmlString => new HtmlString(
                    $this->renderAdminFontStyle() . '
                    <link rel="icon" href="' . asset('images/logo.jpg') . '" media="(prefers-color-scheme: light)">
                    <link rel="icon" href="' . asset('images/dark_logo.png') . '" media="(prefers-color-scheme: dark)">
                '
                ),
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
            ]);
    }

    private function renderAdminFontStyle(): string
    {
        return sprintf(
            '<style id="admin-font-family-override">
                :root{--font-family:%s;}
                .fi-logo, .fi-sidebar-header img, .fi-sidebar-header a img { border-radius: 0.70rem !important; overflow: hidden !important; }
            </style>',
            $this->resolveAdminFontStack(),
        );
    }

    private function resolveAdminFontStack(): string
    {
        try {
            if (!Schema::hasTable('settings')) {
                return AdminFontRegistry::cssStack(null);
            }

            $selected = Setting::query()
                ->where('key', 'admin_font_family')
                ->value('value');

            return AdminFontRegistry::cssStack(is_string($selected) ? $selected : null);
        } catch (\Throwable) {
            return AdminFontRegistry::cssStack(null);
        }
    }
}
