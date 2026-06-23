<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'khmeros', sans-serif; font-size: 10px; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #CFD8DC; padding: 6px; text-align: left; vertical-align: middle; word-wrap: break-word; }
        th { background-color: #E1F5FE; color: #1A237E; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .section-row td { background-color: #E8EAF6; color: #1A237E; font-weight: bold; font-size: 11px; padding: 8px 6px; border-bottom: 2px solid #1A237E; }
        .total-row td { font-weight: bold; border-top: 1px solid #9E9E9E; border-bottom: 3px double #9E9E9E; font-size: 11px; }
        .net-income-row td { font-weight: bold; background-color: #F5F5F5; border-top: 2px solid #1A237E; border-bottom: 3px double #1A237E; font-size: 12px; }
        .kpi-header td { background-color: #E1F5FE; color: #1A237E; font-weight: bold; font-size: 11px; }
        .kpi-sub-header td { background-color: #F5F5F5; color: #1A237E; font-weight: bold; font-size: 10px; }
        .kpi-row td { border-bottom: 1px solid #EEEEEE; font-size: 10px; }
        .kpi-last-row td { background-color: #E8EAF6; font-weight: bold; border-bottom: 1px solid #E0E0E0; }
        .approval-section { margin-top: 20px; border: 1px solid #E0E0E0; padding: 10px; }
        .approval-title { font-weight: bold; color: #1A237E; font-size: 11px; margin-bottom: 15px; }
        .approval-table { width: 100%; border: none; }
        .approval-table td { border: none; text-align: center; width: 33%; padding: 5px; }
        .signature-line { border-bottom: 1px solid #9E9E9E; margin: 30px 20px 5px 20px; }
        .positive { color: #1B5E20; }
        .negative { color: #C62828; }
    </style>
</head>
<body>
    @php
        $currencies = $data['currencies'] ?? ['USD'];
        $revenue = $data['revenue'] ?? [];
        $expenses = $data['expenses'] ?? [];
        $totalRevenue = $data['total_revenue'] ?? [];
        $totalExpenses = $data['total_expenses'] ?? [];
        $netIncome = $data['net_income'] ?? [];
        
        $gRev = $data['grand_total_revenue_usd'] ?? 0;
        $gExp = $data['grand_total_expenses_usd'] ?? 0;
        $gNet = $data['grand_net_income_usd'] ?? 0;
        
        $colSpanLabel = 2; // Arbitrary span for label column to give it more space
        $totalCols = count($currencies) + 2; // Label + currencies + Total USD
    @endphp

    <table>
        <thead>
            <tr>
                <th class="text-left" style="width: 40%"></th>
                @foreach($currencies as $curr)
                <th class="text-right" style="width: {{ 60 / (count($currencies) + 1) }}%">{{ $curr }}</th>
                @endforeach
                <th class="text-right" style="width: {{ 60 / (count($currencies) + 1) }}%">Total USD</th>
            </tr>
        </thead>
        <tbody>
            <!-- REVENUE SECTION -->
            <tr class="section-row">
                <td colspan="{{ count($currencies) + 2 }}">REVENUE</td>
            </tr>
            @foreach($revenue as $item)
            <tr>
                <td class="text-left" style="padding-left: 20px;">{{ $item['label'] ?? '' }}</td>
                @foreach($currencies as $curr)
                <td class="text-right">{{ number_format($item['amounts'][$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right" style="font-weight: bold; color: #1A237E;">{{ number_format($item['total_usd'] ?? 0, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td class="text-left">Total Revenue</td>
                @foreach($currencies as $curr)
                <td class="text-right">{{ number_format($totalRevenue[$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right" style="color: #1A237E;">{{ number_format($gRev, 2) }}</td>
            </tr>

            <tr><td colspan="{{ count($currencies) + 2 }}" style="border: none; padding: 5px;"></td></tr>

            <!-- EXPENSES SECTION -->
            <tr class="section-row">
                <td colspan="{{ count($currencies) + 2 }}">EXPENSES</td>
            </tr>
            @foreach($expenses as $item)
            <tr>
                <td class="text-left" style="padding-left: 20px;">{{ $item['label'] ?? '' }}</td>
                @foreach($currencies as $curr)
                <td class="text-right">{{ number_format($item['amounts'][$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right" style="font-weight: bold; color: #1A237E;">{{ number_format($item['total_usd'] ?? 0, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td class="text-left">Total Operating Expenses</td>
                @foreach($currencies as $curr)
                <td class="text-right">{{ number_format($totalExpenses[$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right" style="color: #1A237E;">{{ number_format($gExp, 2) }}</td>
            </tr>

            <tr><td colspan="{{ count($currencies) + 2 }}" style="border: none; padding: 10px;"></td></tr>

            <!-- NET INCOME -->
            <tr class="net-income-row">
                <td class="text-left">NET INCOME</td>
                @foreach($currencies as $curr)
                <td class="text-right">{{ number_format($netIncome[$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right {{ $gNet >= 0 ? 'positive' : 'negative' }}">{{ number_format($gNet, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <br/>

    <!-- FINANCIAL KPI SUMMARY -->
    @php
        $grossMargin = $gRev > 0 ? (($gRev - $gExp) / $gRev) * 100 : 0;
        $netMargin = $gRev > 0 ? ($gNet / $gRev) * 100 : 0;
        $opRatio = $gRev > 0 ? ($gExp / $gRev) * 100 : 0;
        
        $kpis = [
            ['Total Revenue', number_format($gRev, 2)],
            ['Total Expense', number_format($gExp, 2)],
            ['Gross Profit Margin', number_format($grossMargin, 1) . '%'],
            ['Net Profit Margin', number_format($netMargin, 1) . '%'],
            ['Operation Ratio', number_format($opRatio, 1) . '%'],
            ['Net Profit', number_format($gNet, 2)],
        ];
    @endphp

    <table style="width: 100%; border: 1px solid #90CAF9;">
        <tr class="kpi-header">
            <td colspan="2">Financial KPI Summary</td>
        </tr>
        <tr class="kpi-sub-header">
            <td style="width: 50%;">KPI</td>
            <td class="text-right" style="width: 50%;">Value</td>
        </tr>
        @foreach($kpis as $k => $kpi)
            @php $isLast = ($k == count($kpis) - 1); @endphp
            <tr class="{{ $isLast ? 'kpi-last-row' : 'kpi-row' }}">
                <td>{{ $kpi[0] }}</td>
                <td class="text-right {{ $isLast ? ($gNet >= 0 ? 'positive' : 'negative') : '' }}">{{ $kpi[1] }}</td>
            </tr>
        @endforeach
    </table>

    <!-- APPROVAL SECTION -->
    <div class="approval-section">
        <div class="approval-title">Approval</div>
        <table class="approval-table">
            <tr>
                <td>
                    <div style="font-weight: bold; color: #424242;">Prepared</div>
                    <div class="signature-line"></div>
                    <div>Finance Officer</div>
                    <div style="color: #757575; font-size: 8px;">{{ date('d-m-Y') }}</div>
                </td>
                <td>
                    <div style="font-weight: bold; color: #424242;">Checked By</div>
                    <div class="signature-line"></div>
                    <div>Accounting Manager</div>
                    <div style="color: #757575; font-size: 8px;">{{ date('d-m-Y') }}</div>
                </td>
                <td>
                    <div style="font-weight: bold; color: #424242;">Approved By</div>
                    <div class="signature-line"></div>
                    <div>General Manager</div>
                    <div style="color: #757575; font-size: 8px;">{{ date('d-m-Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
