<x-filament-panels::page>

    @php
        $navItems = [
            ['key' => 'me',            'icon' => 'heroicon-o-user-circle',     'label' => 'My Account'],
            ['key' => 'profile',       'icon' => 'heroicon-o-building-office', 'label' => 'Company Profile'],
            ['key' => 'exchange_rate', 'icon' => 'heroicon-o-currency-dollar', 'label' => 'Exchange Rate'],
            ['key' => 'font',          'icon' => 'heroicon-o-language',        'label' => 'Font Settings'],
            ['key' => 'loan_config',   'icon' => 'heroicon-o-cog-6-tooth',     'label' => 'Loan Config'],
        ];
    @endphp

    <style>
        .settings-layout {
            display: flex;
            gap: 1.5rem;
            align-items: flex-start;
        }
        .settings-sidebar {
            width: 220px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            background: white;
            border-radius: 0.75rem;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            position: sticky;
            top: 1rem;
            max-height: calc(100vh - 8rem);
            overflow-y: auto;
        }
        .dark .settings-sidebar {
            background: #1f2937;
            border-color: #374151;
        }
        .settings-nav-btn {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            width: 100%;
            border-radius: 0.625rem;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-align: left;
            transition: all 0.15s ease;
            border: none;
            cursor: pointer;
            color: #4b5563;
            background: transparent;
        }
        .settings-nav-btn:hover {
            background: #f3f4f6;
            color: #111827;
        }
        .settings-nav-btn.active {
            background: #f0fdf4;
            color: #0d9488;
        }
        .dark .settings-nav-btn {
            color: #9ca3af;
        }
        .dark .settings-nav-btn:hover {
            background: rgba(255,255,255,0.05);
            color: #f9fafb;
        }
        .dark .settings-nav-btn.active {
            background: rgba(13,148,136,0.15);
            color: #2dd4bf;
        }
        .settings-nav-btn .nav-dot {
            margin-left: auto;
            width: 6px;
            height: 6px;
            border-radius: 9999px;
            background: #0d9488;
            flex-shrink: 0;
        }
        .settings-content {
            flex: 1;
            min-width: 0;
        }
        .settings-nav-icon {
            width: 1.125rem;
            height: 1.125rem;
            flex-shrink: 0;
        }
        .settings-actions {
            margin-top: 1.25rem;
            display: flex;
            justify-content: flex-end;
        }
        @media (max-width: 1180px) {
            .settings-layout {
                flex-direction: column;
                gap: 1rem;
            }
            .settings-sidebar {
                width: 100%;
                position: static;
                max-height: none;
                overflow-x: auto;
                overflow-y: hidden;
                flex-direction: row;
                gap: 0.5rem;
                padding: 0.55rem;
                scrollbar-width: none;
            }
            .settings-sidebar::-webkit-scrollbar {
                display: none;
            }
            .settings-nav-btn {
                width: auto;
                min-width: max-content;
                flex: 0 0 auto;
                padding: 0.75rem 0.95rem;
                white-space: nowrap;
            }
        }
        @media (min-width: 768px) and (max-width: 1180px) {
            .settings-sidebar {
                border-radius: 1rem;
                padding: 0.65rem;
            }
            .settings-nav-btn {
                font-size: 0.92rem;
            }
        }
        @media (max-width: 767px) {
            .settings-actions {
                justify-content: stretch;
            }
            .settings-actions .fi-btn {
                width: 100%;
            }
        }
    </style>

    <div class="settings-layout">

        {{-- ── Sidebar ─────────────────────────────────────────────────── --}}
        <nav class="settings-sidebar">
            @foreach ($navItems as $item)
                <button
                    wire:click="$set('activeTab', '{{ $item['key'] }}')"
                    type="button"
                    class="settings-nav-btn {{ $activeTab === $item['key'] ? 'active' : '' }}"
                >
                    <x-filament::icon
                        :icon="$item['icon']"
                        class="settings-nav-icon"
                    />
                    <span>{{ $item['label'] }}</span>
                    @if ($activeTab === $item['key'])
                        <span class="nav-dot"></span>
                    @endif
                </button>
            @endforeach
        </nav>

        {{-- ── Content ──────────────────────────────────────────────────── --}}
        <div class="settings-content">
            <form wire:submit="save">
                {{ $this->form }}

                <div class="settings-actions">
                    <x-filament::button type="submit">
                        Save Settings
                    </x-filament::button>
                </div>
            </form>
        </div>

    </div>

</x-filament-panels::page>
