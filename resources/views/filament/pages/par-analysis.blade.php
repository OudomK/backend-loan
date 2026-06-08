@php
    $metrics = $this->getParMetrics();
    $levels = $metrics['levels'];
    $buckets = $metrics['buckets'];
@endphp

<x-filament-panels::page>
    <style>
        .par-wrap { display: flex; flex-direction: column; gap: 1.5rem; font-family: inherit; }
        
        /* Hero Section */
        .par-hero {
            position: relative; overflow: hidden; border-radius: 1rem;
            background: linear-gradient(135deg, rgba(15,23,42,0.95), rgba(30,41,59,0.95));
            border: 1px solid rgba(245,158,11,0.25);
            padding: 2rem; color: white;
            box-shadow: 0 0 30px rgba(245,158,11,0.08);
        }
        .par-hero::before {
            content: ''; position: absolute; right: -5rem; top: -5rem; width: 16rem; height: 16rem;
            border-radius: 9999px; background: rgba(245,158,11,0.08); filter: blur(50px);
        }
        .par-hero::after {
            content: ''; position: absolute; left: -2.5rem; bottom: -8rem; width: 20rem; height: 20rem;
            border-radius: 9999px; background: rgba(59,130,246,0.06); filter: blur(50px);
        }
        .par-hero-content { position: relative; z-index: 10; display: flex; flex-direction: column; gap: 1.5rem; }
        @media (min-width: 768px) { .par-hero-content { flex-direction: row; justify-content: space-between; align-items: center; } }
        .par-hero h2 { margin: 0; font-size: 1.875rem; font-weight: 900; letter-spacing: -0.025em; line-height: 1.2; color: #fff; }
        .par-hero-date { margin-top: 0.5rem; font-weight: 500; color: rgba(255,255,255,0.6); font-size: 0.95rem; }
        .par-hero-total-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.5); }
        .par-hero-total-val { margin-top: 0.25rem; font-size: 2.25rem; font-weight: 900; letter-spacing: -0.025em; line-height: 1; color: #f5f5f5; }
        
        .par-hero-footer {
            position: relative; z-index: 10; margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 1.5rem; font-size: 0.875rem; font-weight: 500; color: rgba(255,255,255,0.75);
        }
        .par-hero-footer-item { display: flex; align-items: center; gap: 0.5rem; }
        .par-hero-footer-item svg { width: 1.25rem; height: 1.25rem; opacity: 0.5; }

        /* Grid */
        .par-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
        @media (min-width: 768px) { .par-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1280px) { .par-grid { grid-template-columns: repeat(4, 1fr); } }

        /* Cards — dark glass style */
        .par-card {
            position: relative; overflow: hidden; border-radius: 1rem;
            background: rgba(15,23,42,0.72);
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.3s ease;
        }
        .par-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(0,0,0,0.35);
            border-color: rgba(255,255,255,0.15);
        }
        
        .par-card-blob {
            position: absolute; right: -1.5rem; top: -1.5rem; width: 6rem; height: 6rem;
            border-radius: 9999px; transition: transform 0.5s ease; filter: blur(8px);
        }
        .par-card:hover .par-card-blob { transform: scale(1.8); }
        .par-card-content { position: relative; z-index: 10; }
        
        .par-card-header { display: flex; align-items: center; justify-content: space-between; }
        .par-card-title { font-weight: 700; color: rgba(255,255,255,0.6); margin: 0; font-size: 0.9rem; letter-spacing: 0.03em; text-transform: uppercase; }
        
        .par-badge {
            display: inline-flex; align-items: center; border-radius: 9999px; padding: 0.25rem 0.7rem;
            font-size: 0.75rem; font-weight: 800; border: 1px solid transparent;
        }

        /* PAR Card color variants */
        .par-blob-amber { background: rgba(245,158,11,0.2); }
        .par-blob-orange { background: rgba(249,115,22,0.2); }
        .par-blob-red { background: rgba(239,68,68,0.2); }
        .par-blob-rose { background: rgba(225,29,72,0.2); }

        .par-badge-amber { background: rgba(245,158,11,0.15); color: #fbbf24; border-color: rgba(245,158,11,0.3); }
        .par-badge-orange { background: rgba(249,115,22,0.15); color: #fb923c; border-color: rgba(249,115,22,0.3); }
        .par-badge-red { background: rgba(239,68,68,0.15); color: #f87171; border-color: rgba(239,68,68,0.3); }
        .par-badge-rose { background: rgba(225,29,72,0.15); color: #fb7185; border-color: rgba(225,29,72,0.3); }

        /* Classification badge colors */
        .par-badge-standard { background: rgba(16,185,129,0.15); color: #34d399; border-color: rgba(16,185,129,0.3); }
        .par-badge-special { background: rgba(245,158,11,0.15); color: #fbbf24; border-color: rgba(245,158,11,0.3); }
        .par-badge-substandard { background: rgba(249,115,22,0.15); color: #fb923c; border-color: rgba(249,115,22,0.3); }
        .par-badge-doubtful { background: rgba(239,68,68,0.15); color: #f87171; border-color: rgba(239,68,68,0.3); }
        .par-badge-loss { background: rgba(225,29,72,0.15); color: #fb7185; border-color: rgba(225,29,72,0.3); }
        .par-badge-gray { background: rgba(107,114,128,0.15); color: #9ca3af; border-color: rgba(107,114,128,0.3); }

        .par-val-main { margin-top: 1rem; font-size: 1.5rem; font-weight: 900; color: #ffffff; line-height: 1; }

        .par-card-stats { margin-top: 1.5rem; font-size: 0.875rem; }
        .par-stat-row { display: flex; justify-content: space-between; padding: 0.35rem 0; }
        .par-stat-row.bordered { border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 0.5rem; margin-bottom: 0.5rem; }
        .par-stat-label { color: rgba(255,255,255,0.5); }
        .par-stat-val { font-weight: 600; color: rgba(255,255,255,0.75); }
        .par-stat-val.bold { font-weight: 800; color: #ffffff; }

        /* Table — dark glass style */
        .par-table-wrap {
            border-radius: 1rem; overflow: hidden;
            background: rgba(15,23,42,0.72);
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .par-table-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
        }
        .par-table-title { margin: 0; font-size: 1.125rem; font-weight: 800; color: #ffffff; }

        .par-table-container { overflow-x: auto; }
        .par-table { width: 100%; text-align: left; font-size: 0.875rem; border-collapse: collapse; white-space: nowrap; }
        .par-table th {
            padding: 1rem 1.5rem; font-weight: 800; text-transform: uppercase; font-size: 0.7rem;
            letter-spacing: 0.08em; color: rgba(255,255,255,0.45);
            background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .par-table th.right, .par-table td.right { text-align: right; }
        
        .par-table td {
            padding: 1rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.7); transition: background-color 0.2s;
        }
        .par-table tr:hover td { background-color: rgba(255,255,255,0.04); }
        .par-table tr:last-child td { border-bottom: none; }
        .par-table td.bold { font-weight: 800; color: #ffffff; }

        .par-progress-wrap { display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; }
        .par-progress-bar { width: 5rem; height: 0.5rem; border-radius: 9999px; background: rgba(255,255,255,0.1); overflow: hidden; }
        .par-progress-fill { height: 100%; border-radius: 9999px; background: linear-gradient(90deg, #0ea5e9, #06b6d4); transition: width 1s ease; }
    </style>

    <div class="par-wrap">
        
        <!-- Hero Section -->
        <div class="par-hero">
            <div class="par-hero-content">
                <div>
                    <h2>Portfolio At Risk (PAR)</h2>
                    <p class="par-hero-date">Snapshot as of {{ $metrics['reference_date'] }}</p>
                </div>
                <div class="right">
                    <div class="par-hero-total-label">Total USD Equivalent</div>
                    <div class="par-hero-total-val">${{ number_format($metrics['portfolio_usd_equivalent'], 2) }}</div>
                </div>
            </div>
                
            <div class="par-hero-footer">
                <div class="par-hero-footer-item">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>USD: ${{ number_format($metrics['portfolio_usd'], 2) }}</span>
                </div>
                <div class="par-hero-footer-item">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V5.942c0-.758-.727-1.297-1.453-1.096V5.942a60.07 60.07 0 01-15.797 2.101c-.727.198-1.453-.342-1.453-1.096v11.854c0 .754.727 1.294 1.453 1.096z" /></svg>
                    <span>KHR: {{ number_format($metrics['portfolio_khr'], 2) }} ៛</span>
                </div>
            </div>
        </div>

        <!-- PAR Cards Grid -->
        <div class="par-grid">
            @foreach ([
                ['key' => 'par1', 'label' => 'PAR 1%', 'blob' => 'par-blob-amber', 'badge' => 'par-badge-amber'],
                ['key' => 'par30', 'label' => 'PAR 30%', 'blob' => 'par-blob-orange', 'badge' => 'par-badge-orange'],
                ['key' => 'par60', 'label' => 'PAR 60%', 'blob' => 'par-blob-red', 'badge' => 'par-badge-red'],
                ['key' => 'par90', 'label' => 'PAR 90%', 'blob' => 'par-blob-rose', 'badge' => 'par-badge-rose'],
            ] as $config)
                @php $k = $config['key']; @endphp
                <div class="par-card">
                    <div class="par-card-blob {{ $config['blob'] }}"></div>
                    <div class="par-card-content">
                        <div class="par-card-header">
                            <h3 class="par-card-title">{{ $config['label'] }}</h3>
                            <span class="par-badge {{ $config['badge'] }}">
                                {{ number_format($levels[$k]['percent'], 2) }}%
                            </span>
                        </div>
                        
                        <div class="par-val-main">
                            ${{ number_format($levels[$k]['usd_equivalent'], 2) }}
                        </div>
                        
                        <div class="par-card-stats">
                            <div class="par-stat-row bordered">
                                <span class="par-stat-label">At Risk Count</span>
                                <span class="par-stat-val bold">{{ number_format($levels[$k]['count']) }}</span>
                            </div>
                            <div class="par-stat-row">
                                <span class="par-stat-label">USD</span>
                                <span class="par-stat-val">${{ number_format($levels[$k]['usd'], 2) }}</span>
                            </div>
                            <div class="par-stat-row">
                                <span class="par-stat-label">KHR</span>
                                <span class="par-stat-val">{{ number_format($levels[$k]['khr'], 2) }} ៛</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Aging Bucket Table -->
        <div class="par-table-wrap">
            <div class="par-table-header">
                <h3 class="par-table-title">Portfolio Classification Breakdown</h3>
            </div>
            
            <div class="par-table-container">
                <table class="par-table">
                    <thead>
                        <tr>
                            <th>Classification</th>
                            <th class="right">Accounts</th>
                            <th class="right">USD Amount</th>
                            <th class="right">KHR Amount</th>
                            <th class="right">USD Equivalent</th>
                            <th class="right">Portfolio Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($buckets as $key => $bucket)
                            @php
                                $badgeClass = match(true) {
                                    str_contains(strtolower($bucket['label']), 'special mention') => 'par-badge-special',
                                    str_contains(strtolower($bucket['label']), 'substandard') => 'par-badge-substandard',
                                    str_contains(strtolower($bucket['label']), 'standard') => 'par-badge-standard',
                                    str_contains(strtolower($bucket['label']), 'doubtful') => 'par-badge-doubtful',
                                    str_contains(strtolower($bucket['label']), 'loss') => 'par-badge-loss',
                                    default => 'par-badge-gray',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="par-badge {{ $badgeClass }}">
                                        {{ $bucket['label'] }}
                                    </span>
                                </td>
                                <td class="right bold">{{ number_format($bucket['count']) }}</td>
                                <td class="right">${{ number_format($bucket['usd'], 2) }}</td>
                                <td class="right">{{ number_format($bucket['khr'], 2) }} ៛</td>
                                <td class="right bold">${{ number_format($bucket['usd_equivalent'], 2) }}</td>
                                <td class="right">
                                    <div class="par-progress-wrap">
                                        <span class="bold" style="width: 3rem;">{{ number_format($bucket['share_percent'], 2) }}%</span>
                                        <div class="par-progress-bar" x-data="{ w: {{ min(100, $bucket['share_percent']) }} }">
                                            <div class="par-progress-fill" x-bind:style="`width: ${w}%`"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
