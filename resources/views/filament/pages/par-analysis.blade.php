@php
    $metrics = $this->getParMetrics();
    $levels = $metrics['levels'];
    $buckets = $metrics['buckets'];
@endphp

<x-filament-panels::page>
    <style>
        .par-wrap { display: grid; gap: 1.25rem; }
        .par-hero {
            border-radius: 1rem;
            padding: 1.25rem;
            border: 1px solid rgba(245, 158, 11, 0.25);
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 41, 59, 0.96));
            color: white;
        }
        .par-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }
        .par-card {
            border-radius: 1rem;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(15, 23, 42, 0.72);
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.28);
        }
        .par-label {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.72);
        }
        .par-value {
            margin: 0.35rem 0 0;
            font-size: 2rem;
            line-height: 1.1;
            font-weight: 800;
            color: #fff;
        }
        .par-meta {
            margin-top: 0.7rem;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.82);
            line-height: 1.55;
        }
        .par-note {
            border-radius: 1rem;
            padding: 1rem 1.1rem;
            border: 1px solid rgba(59, 130, 246, 0.18);
            background: rgba(37, 99, 235, 0.08);
            color: rgb(226, 232, 240);
        }
        .par-table-wrap {
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(15, 23, 42, 0.72);
        }
        .par-table-head {
            padding: 1rem 1.1rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            color: #fff;
        }
        .par-table {
            width: 100%;
            border-collapse: collapse;
        }
        .par-table th,
        .par-table td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            text-align: left;
            color: rgba(255,255,255,0.88);
            font-size: 0.92rem;
        }
        .par-table th {
            color: rgba(255,255,255,0.68);
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
            background: rgba(255,255,255,0.03);
        }
        .par-table tr:last-child td {
            border-bottom: none;
        }
        @media (max-width: 1100px) {
            .par-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 700px) {
            .par-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="par-wrap">
        <div class="par-hero">
            <h2 style="margin:0;font-size:1.75rem;font-weight:800;">PAR Analysis</h2>
            <p style="margin:0.45rem 0 0;color:rgba(255,255,255,0.78);">
                Snapshot as of {{ $metrics['reference_date'] }}
            </p>
            <p style="margin:0.55rem 0 0;color:rgba(255,255,255,0.88);font-weight:600;">
                Portfolio OS: USD {{ number_format($metrics['portfolio_usd'], 2) }} • KHR {{ number_format($metrics['portfolio_khr'], 2) }}
            </p>
            <p style="margin:0.35rem 0 0;color:rgba(255,255,255,0.72);font-weight:500;">
                Combined portfolio (USD equivalent): {{ number_format($metrics['portfolio_usd_equivalent'], 2) }}
            </p>
        </div>

        <div class="par-grid">
            @foreach ([
                'par1' => 'PAR 1%',
                'par30' => 'PAR 30%',
                'par60' => 'PAR 60%',
                'par90' => 'PAR 90%',
            ] as $key => $label)
                <div class="par-card">
                    <p class="par-label">{{ $label }}</p>
                    <p class="par-value">{{ number_format($levels[$key]['percent'], 2) }}%</p>
                    <div class="par-meta">
                        <div>Loans at risk: {{ number_format($levels[$key]['count']) }}</div>
                        <div>USD at risk: {{ number_format($levels[$key]['usd'], 2) }}</div>
                        <div>KHR at risk: {{ number_format($levels[$key]['khr'], 2) }}</div>
                        <div>USD equiv.: {{ number_format($levels[$key]['usd_equivalent'], 2) }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="par-table-wrap">
            <div class="par-table-head">
                <h3 style="margin:0;font-size:1.05rem;font-weight:800;">Aging Bucket Breakdown</h3>

            </div>
            <table class="par-table">
                <thead>
                    <tr>
                        <th>Bucket</th>
                        <th>Loan Count</th>
                        <th>USD At Risk</th>
                        <th>KHR At Risk</th>
                        <th>USD Equivalent</th>
                        <th>Portfolio Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($buckets as $bucket)
                        <tr>
                            <td>{{ $bucket['label'] }}</td>
                            <td>{{ number_format($bucket['count']) }}</td>
                            <td>{{ number_format($bucket['usd'], 2) }}</td>
                            <td>{{ number_format($bucket['khr'], 2) }}</td>
                            <td>{{ number_format($bucket['usd_equivalent'], 2) }}</td>
                            <td>{{ number_format($bucket['share_percent'], 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>


    </div>
</x-filament-panels::page>
