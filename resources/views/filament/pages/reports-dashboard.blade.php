@php
    $groups = $this->getReportGroups();
    $stats = $this->getReportStats();
@endphp

<x-filament-panels::page>
    <style>
        .rd-wrap {
            display: grid;
            gap: 1.25rem;
        }

        .rd-hero {
            border-radius: 1.25rem;
            border: 1px solid rgba(245, 158, 11, 0.28);
            background: linear-gradient(120deg, rgba(245, 158, 11, 0.18), rgba(15, 23, 42, 0.8), rgba(59, 130, 246, 0.2));
            padding: 1.4rem;
            box-shadow: 0 18px 40px rgba(3, 7, 18, 0.45);
        }

        .rd-hero-top {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .rd-title-wrap {
            max-width: 46rem;
        }

        .rd-date {
            margin: 0;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .rd-title {
            margin: 0.25rem 0 0;
            color: #ffffff;
            font-size: 2rem;
            line-height: 1.1;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .rd-subtitle {
            margin: 0.5rem 0 0;
            color: rgba(255, 255, 255, 0.88);
            font-size: 1rem;
            line-height: 1.5;
        }

        .rd-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.65rem;
            width: min(100%, 31rem);
        }

        .rd-stat {
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(2, 6, 23, 0.62);
            padding: 0.7rem 0.75rem;
        }

        .rd-stat-label {
            margin: 0;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .rd-stat-value {
            margin: 0.2rem 0 0;
            color: #ffffff;
            font-size: 1.65rem;
            line-height: 1.1;
            font-weight: 700;
        }

        .rd-stat-api {
            border-color: rgba(56, 189, 248, 0.35);
            background: rgba(14, 116, 144, 0.2);
        }

        .rd-stat-excel {
            border-color: rgba(52, 211, 153, 0.35);
            background: rgba(6, 95, 70, 0.2);
        }

        .rd-stat-pdf {
            border-color: rgba(251, 113, 133, 0.35);
            background: rgba(159, 18, 57, 0.2);
        }

        .rd-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .rd-group {
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(15, 23, 42, 0.62);
            overflow: hidden;
            box-shadow: 0 14px 28px rgba(2, 6, 23, 0.3);
        }

        .rd-group-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.9rem;
        }

        .rd-group-meta {
            display: flex;
            gap: 0.65rem;
            align-items: flex-start;
        }

        .rd-group-icon {
            width: 2.2rem;
            height: 2.2rem;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border-radius: 0.7rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.06);
            color: rgb(252, 211, 77);
            flex-shrink: 0;
        }

        .rd-group-title {
            margin: 0;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
        }

        .rd-group-desc {
            margin: 0.2rem 0 0;
            color: rgba(255, 255, 255, 0.68);
            font-size: 0.8rem;
            line-height: 1.45;
        }

        .rd-group-count {
            border-radius: 0.45rem;
            border: 1px solid rgba(245, 158, 11, 0.35);
            background: rgba(245, 158, 11, 0.14);
            padding: 0.28rem 0.45rem;
            color: rgb(253, 230, 138);
            font-size: 0.64rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
            white-space: nowrap;
        }

        .rd-list {
            display: grid;
            gap: 0.65rem;
            padding: 0.9rem;
        }

        .rd-item {
            text-decoration: none;
            display: block;
            border-radius: 0.78rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.03);
            padding: 0.7rem;
            transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease;
        }

        .rd-item:hover {
            transform: translateY(-2px);
            border-color: rgba(245, 158, 11, 0.35);
            background: rgba(255, 255, 255, 0.06);
        }

        .rd-item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.65rem;
        }

        .rd-item-left {
            display: flex;
            gap: 0.65rem;
            min-width: 0;
        }

        .rd-item-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 0.6rem;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.06);
            color: #dbeafe;
            flex-shrink: 0;
        }

        .rd-tone-success {
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.4);
            background: rgba(16, 185, 129, 0.14);
        }

        .rd-tone-primary {
            color: #93c5fd;
            border-color: rgba(59, 130, 246, 0.4);
            background: rgba(59, 130, 246, 0.14);
        }

        .rd-tone-danger {
            color: #fda4af;
            border-color: rgba(244, 63, 94, 0.4);
            background: rgba(244, 63, 94, 0.14);
        }

        .rd-tone-warning {
            color: #fcd34d;
            border-color: rgba(245, 158, 11, 0.42);
            background: rgba(245, 158, 11, 0.14);
        }

        .rd-tone-gray {
            color: #d1d5db;
            border-color: rgba(148, 163, 184, 0.35);
            background: rgba(100, 116, 139, 0.16);
        }

        .rd-tone-indigo {
            color: #c4b5fd;
            border-color: rgba(129, 140, 248, 0.38);
            background: rgba(129, 140, 248, 0.14);
        }

        .rd-item-title {
            margin: 0;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.3;
        }

        .rd-item-desc {
            margin: 0.2rem 0 0;
            color: rgba(255, 255, 255, 0.67);
            font-size: 0.8rem;
            line-height: 1.45;
        }

        .rd-open-icon {
            color: rgba(255, 255, 255, 0.48);
            flex-shrink: 0;
            margin-top: 0.15rem;
        }

        .rd-item:hover .rd-open-icon {
            color: #fde68a;
        }

        .rd-item-foot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.62rem;
        }

        .rd-type {
            border-radius: 0.45rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            padding: 0.2rem 0.42rem;
            color: #e2e8f0;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .rd-type-api {
            border-color: rgba(56, 189, 248, 0.35);
            background: rgba(14, 116, 144, 0.22);
            color: rgb(186, 230, 253);
        }

        .rd-type-excel {
            border-color: rgba(52, 211, 153, 0.35);
            background: rgba(6, 95, 70, 0.22);
            color: rgb(167, 243, 208);
        }

        .rd-type-pdf {
            border-color: rgba(251, 113, 133, 0.35);
            background: rgba(159, 18, 57, 0.22);
            color: rgb(254, 205, 211);
        }

        .rd-open-text {
            color: rgba(255, 255, 255, 0.56);
            font-size: 0.73rem;
        }

        .rd-item:hover .rd-open-text {
            color: rgb(253, 230, 138);
        }

        .rd-empty {
            border-radius: 0.78rem;
            border: 1px dashed rgba(255, 255, 255, 0.2);
            padding: 1.1rem;
            color: rgba(255, 255, 255, 0.62);
            text-align: center;
            font-size: 0.85rem;
        }

        @media (max-width: 1280px) {
            .rd-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 1180px) {
            .rd-hero {
                padding: 1.15rem;
            }

            .rd-title-wrap {
                max-width: 100%;
            }

            .rd-stats {
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .rd-group-head {
                padding: 0.85rem;
            }

            .rd-list {
                padding: 0.85rem;
            }
        }

        @media (max-width: 900px) {
            .rd-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .rd-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .rd-title {
                font-size: 1.62rem;
            }
        }
    </style>

    <div class="rd-wrap">
        <section class="rd-hero">
            <div class="rd-hero-top">
                <div class="rd-title-wrap">
                    <p class="rd-date">{{ now()->format('l, d M Y') }}</p>
                    <h2 class="rd-title">Report Command Center</h2>
                    <p class="rd-subtitle">
                        Access operational, financial, and administrative reports from one clean workspace.
                    </p>
                </div>

                <div class="rd-stats">
                    <div class="rd-stat">
                        <p class="rd-stat-label">All Reports</p>
                        <p class="rd-stat-value">{{ number_format($stats['total']) }}</p>
                    </div>
                    <div class="rd-stat rd-stat-api">
                        <p class="rd-stat-label">API</p>
                        <p class="rd-stat-value">{{ number_format($stats['api']) }}</p>
                    </div>
                    <div class="rd-stat rd-stat-excel">
                        <p class="rd-stat-label">Excel</p>
                        <p class="rd-stat-value">{{ number_format($stats['excel']) }}</p>
                    </div>
                    <div class="rd-stat rd-stat-pdf">
                        <p class="rd-stat-label">PDF</p>
                        <p class="rd-stat-value">{{ number_format($stats['pdf']) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rd-grid">
            @foreach($groups as $group)
                @php /** @var array{label:string, description:string, icon:string, reports:array<int, array{name:string,description:string,url:string,type:string,icon:string,color:string}>} $group */ @endphp
                <article class="rd-group">
                    <header class="rd-group-head">
                        <div class="rd-group-meta">
                            <span class="rd-group-icon">
                                <x-filament::icon :icon="$group['icon']" style="width: 1.1rem; height: 1.1rem;" />
                            </span>
                            <div>
                                <h3 class="rd-group-title">{{ $group['label'] }}</h3>
                                <p class="rd-group-desc">{{ $group['description'] }}</p>
                            </div>
                        </div>
                        <span class="rd-group-count">{{ count($group['reports']) }} reports</span>
                    </header>

                    <div class="rd-list">
                        @forelse($group['reports'] as $report)
                            @php
                                $toneClass = match ($report['color']) {
                                    'success' => 'rd-tone-success',
                                    'primary' => 'rd-tone-primary',
                                    'danger' => 'rd-tone-danger',
                                    'warning' => 'rd-tone-warning',
                                    'gray' => 'rd-tone-gray',
                                    'indigo' => 'rd-tone-indigo',
                                    default => '',
                                };

                                $typeClass = match ($report['type']) {
                                    'API' => 'rd-type-api',
                                    'Excel' => 'rd-type-excel',
                                    'PDF', 'PDF/Print' => 'rd-type-pdf',
                                    default => '',
                                };
                            @endphp
                            <a href="{{ $report['url'] }}" target="_blank" rel="noopener noreferrer" class="rd-item">
                                <div class="rd-item-row">
                                    <div class="rd-item-left">
                                        <span class="rd-item-icon {{ $toneClass }}">
                                            <x-filament::icon :icon="$report['icon']" style="width: 1rem; height: 1rem;" />
                                        </span>
                                        <div>
                                            <p class="rd-item-title">{{ $report['name'] }}</p>
                                            <p class="rd-item-desc">{{ $report['description'] }}</p>
                                        </div>
                                    </div>
                                    <span class="rd-open-icon">
                                        <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" style="width: 1rem; height: 1rem;" />
                                    </span>
                                </div>

                                <div class="rd-item-foot">
                                    <span class="rd-type {{ $typeClass }}">{{ $report['type'] }}</span>
                                    <span class="rd-open-text">Open report</span>
                                </div>
                            </a>
                        @empty
                            <div class="rd-empty">
                                No reports available in this category.
                            </div>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
