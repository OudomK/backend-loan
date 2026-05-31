<x-filament-panels::page>
    <style>
        .cf-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.9rem;
            margin-bottom: 1rem;
        }

        .cf-stat {
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(10, 15, 28, 0.76);
            padding: 0.95rem 1rem;
            box-shadow: 0 14px 30px rgba(2, 6, 23, 0.24);
        }

        .cf-stat-label {
            margin: 0;
            color: rgba(203, 213, 225, 0.72);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .cf-stat-value {
            margin: 0.25rem 0 0;
            color: #f8fafc;
            font-size: 1.5rem;
            line-height: 1.1;
            font-weight: 800;
        }

        .cf-stat-note {
            margin: 0.3rem 0 0;
            color: rgba(148, 163, 184, 0.92);
            font-size: 0.82rem;
            line-height: 1.45;
        }

        .fi-page-header-main-ctn .fi-header {
            margin-bottom: 1.25rem;
        }

        .fi-page-header-main-ctn .fi-header-heading {
            font-size: clamp(2.15rem, 3vw, 3.25rem);
            line-height: 1.02;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #f8fafc;
        }

        .fi-page-header-main-ctn .fi-header-subheading {
            margin-top: 0.55rem;
            max-width: 46rem;
            color: rgba(226, 232, 240, 0.78);
            font-size: 0.98rem;
            line-height: 1.6;
        }

        .fi-page-header-main-ctn .fi-breadcrumbs {
            margin-bottom: 0.35rem;
        }

        .fi-page-header-main-ctn .fi-header-actions-ctn {
            align-self: flex-end;
        }

        .cf-table-card {
            border-radius: 1.5rem;
            border: 1px solid rgba(56, 189, 248, 0.12);
            background: linear-gradient(180deg, rgba(10, 15, 28, 0.96), rgba(12, 18, 32, 0.92));
            box-shadow: 0 24px 54px rgba(2, 6, 23, 0.34);
            overflow: hidden;
        }

        .cf-table-card :is(.fi-ta, .fi-ta-content, .fi-ta-header) {
            background: transparent;
        }

        .cf-table-card .fi-ta-header {
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
        }

        .cf-table-card .fi-ta-header,
        .cf-table-card .fi-ta-header-toolbar,
        .cf-table-card .fi-ta-content,
        .cf-table-card .fi-ta-empty-state {
            color: #e5e7eb;
        }

        .cf-table-card .fi-ta-empty-state {
            padding-top: 4rem;
            padding-bottom: 4rem;
        }

        @media (max-width: 1024px) {
            .cf-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .cf-stats {
                grid-template-columns: 1fr;
            }

            .cf-table-card {
                border-radius: 1.1rem;
            }
        }
    </style>

    <div class="cf-stats">
        <div class="cf-stat">
            <p class="cf-stat-label">System Fonts</p>
            <p class="cf-stat-value">{{ \App\Support\AdminFontRegistry::coreCount() }}</p>
            <p class="cf-stat-note">Built-in fonts shipped with the app.</p>
        </div>

        <div class="cf-stat">
            <p class="cf-stat-label">Imported Active</p>
            <p class="cf-stat-value">{{ \App\Support\AdminFontRegistry::activeCustomCount() }}</p>
            <p class="cf-stat-note">Custom fonts currently enabled.</p>
        </div>

        <div class="cf-stat">
            <p class="cf-stat-label">Total Available</p>
            <p class="cf-stat-value">{{ \App\Support\AdminFontRegistry::count() }}</p>
            <p class="cf-stat-note">System fonts + active imported fonts.</p>
        </div>
    </div>

    <div class="cf-table-card">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
