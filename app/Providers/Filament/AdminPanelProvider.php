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
                    <script data-navigate-once>
                        (() => {
                            const DEFAULT_WIDTH = 248
                            const MIN_WIDTH = 216
                            const MAX_WIDTH = 340
                            const STORAGE_KEY = 'fi-sidebar-width-admin'
                            const root = document.documentElement
                            const resizableQuery = window.matchMedia('(min-width: 480px)')
                            const getEffectiveMaxWidth = () => Math.max(
                                MIN_WIDTH + 24,
                                Math.min(MAX_WIDTH, Math.round(window.innerWidth * 0.32)),
                            )
                            const normalizeWidth = (value) => clamp(Math.round(value), MIN_WIDTH, getEffectiveMaxWidth())

                            // Reset invalid values that were saved by older buggy code.
                            const savedWidth = Number(localStorage.getItem(STORAGE_KEY))

                            if (! Number.isFinite(savedWidth) || savedWidth < MIN_WIDTH || savedWidth > getEffectiveMaxWidth()) {
                                localStorage.removeItem(STORAGE_KEY)
                            }

                            let isDragging = false
                            let handle = null
                            let hasSidebarObserver = false

                            const isRtl = () => root.getAttribute('dir') === 'rtl'
                            const clamp = (value, min, max) => Math.min(Math.max(value, min), max)

                            const getCurrentWidth = () => {
                                const raw = getComputedStyle(root).getPropertyValue('--sidebar-width').trim()

                                if (! raw) {
                                    return DEFAULT_WIDTH
                                }

                                if (raw.endsWith('rem')) {
                                    const rem = Number.parseFloat(raw)
                                    const base = Number.parseFloat(getComputedStyle(root).fontSize) || 16

                                    return Number.isFinite(rem) ? rem * base : DEFAULT_WIDTH
                                }

                                const pixels = Number.parseFloat(raw)

                                return Number.isFinite(pixels) ? pixels : DEFAULT_WIDTH
                            }

                            const applyWidth = (value, shouldPersist = true) => {
                                const width = normalizeWidth(value)
                                root.style.setProperty('--sidebar-width', `${width}px`)

                                if (shouldPersist) {
                                    localStorage.setItem(STORAGE_KEY, `${width}`)
                                }
                            }

                            const hasSidebar = () => Boolean(document.querySelector('.fi-sidebar'))


                            const syncHandleVisibility = () => {
                                const shouldShow =
                                    resizableQuery.matches &&
                                    hasSidebar() &&
                                    ! document.body.classList.contains('fi-body-has-top-navigation')

                                if (! handle) {
                                    return
                                }

                                handle.classList.toggle('fi-hidden', ! shouldShow)
                            }

                            const getClientX = (event) => {
                                if (event.touches?.length) {
                                    return event.touches[0].clientX
                                }

                                if (event.changedTouches?.length) {
                                    return event.changedTouches[0].clientX
                                }

                                return event.clientX
                            }

                            const startDrag = (event) => {
                                if (! resizableQuery.matches || ! hasSidebar()) {
                                    return
                                }

                                if (event.cancelable) {
                                    event.preventDefault()
                                }

                                isDragging = true
                                document.body.classList.add('fi-sidebar-resizing')
                                handle?.classList.add('fi-active')
                            }

                            const onDrag = (event) => {
                                if (! isDragging) {
                                    return
                                }

                                if (event.cancelable) {
                                    event.preventDefault()
                                }

                                const clientX = getClientX(event)
                                const width = isRtl() ? window.innerWidth - clientX : clientX

                                applyWidth(width)
                            }

                            const stopDrag = () => {
                                if (! isDragging) {
                                    return
                                }

                                isDragging = false
                                document.body.classList.remove('fi-sidebar-resizing')
                                handle?.classList.remove('fi-active')
                                syncHandleVisibility()
                            }

                            const ensureHandle = () => {
                                if (handle && document.body.contains(handle)) {
                                    return
                                }

                                handle = document.createElement('button')
                                handle.type = 'button'
                                handle.className = 'fi-sidebar-resizer'
                                handle.setAttribute('aria-label', 'Resize sidebar')
                                handle.setAttribute('aria-orientation', 'vertical')
                                handle.setAttribute('title', 'Drag to resize sidebar')
                                handle.addEventListener('mousedown', startDrag)
                                handle.addEventListener('touchstart', startDrag, { passive: false })
                                handle.addEventListener('keydown', (event) => {
                                    if (! ['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                                        return
                                    }

                                    event.preventDefault()

                                    const step = event.shiftKey ? 24 : 12
                                    const direction = isRtl()
                                        ? event.key === 'ArrowLeft' ? 1 : -1
                                        : event.key === 'ArrowRight' ? 1 : -1

                                    applyWidth(getCurrentWidth() + (step * direction))
                                })

                                document.body.appendChild(handle)
                            }

                            const attachSidebarObserver = () => {
                                if (hasSidebarObserver) {
                                    return
                                }

                                const sidebar = document.querySelector('.fi-sidebar')

                                if (! sidebar) {
                                    return
                                }

                                const observer = new MutationObserver(syncHandleVisibility)
                                observer.observe(sidebar, {
                                    attributes: true,
                                    attributeFilter: ['class'],
                                })

                                hasSidebarObserver = true
                            }

                            const init = () => {
                                if (! hasSidebar()) {
                                    return
                                }

                                const saved = Number(localStorage.getItem(STORAGE_KEY))

                                if (Number.isFinite(saved) && saved > 0) {
                                    applyWidth(saved, false)
                                } else {
                                    applyWidth(DEFAULT_WIDTH, false)
                                }

                                ensureHandle()
                                attachSidebarObserver()
                                syncHandleVisibility()
                            }

                            window.addEventListener('resize', () => {
                                applyWidth(getCurrentWidth())
                                syncHandleVisibility()
                            })
                            document.addEventListener('mousemove', onDrag)
                            document.addEventListener('touchmove', onDrag, { passive: false })
                            document.addEventListener('mouseup', stopDrag)
                            document.addEventListener('touchend', stopDrag)
                            document.addEventListener('touchcancel', stopDrag)
                            document.addEventListener('livewire:navigated', init)

                            init()
                        })()
                    </script>
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
