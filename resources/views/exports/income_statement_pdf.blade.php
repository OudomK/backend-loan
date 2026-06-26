<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'khmeros', sans-serif;
            font-size: 9px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* Main table */
        .main-table {
            width: 100%;
            border-collapse: collapse;
        }

        .main-table th {
            background-color: #D3D3D3;
            color: #000;
            font-weight: bold;
            font-size: 9px;
            padding: 5px 8px;
            border: 1px solid #000;
            text-align: center;
        }

        .main-table td {
            padding: 4px 8px;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 0.5px solid #CCC;
            font-size: 9px;
        }

        /* Section headers */
        .section-hdr td {
            background-color: #DCEEFF;
            font-weight: bold;
            font-size: 9px;
            padding: 5px 8px;
            border-bottom: 1px solid #999;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
        }

        /* Data rows */
        .data-row td {
            background-color: #FFF;
        }

        .data-row td.lbl {
            padding-left: 20px;
        }

        .data-row td.amt {
            text-align: right;
        }

        .data-row td.total-col {
            text-align: right;
            font-weight: bold;
        }

        /* Subtotal rows */
        .sub-total td {
            font-weight: bold;
            background-color: #F0F0F0;
            border-top: 1px solid #000;
            border-bottom: 2px double #000;
            padding: 5px 8px;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
        }

        .sub-total td.amt,
        .sub-total td.total-col {
            text-align: right;
        }

        /* Net income */
        .net-row td {
            font-weight: bold;
            font-size: 10px;
            background-color: #E0E0E0;
            border-top: 2px solid #000;
            border-bottom: 3px double #000;
            padding: 6px 8px;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
        }

        .net-row td.amt,
        .net-row td.total-col {
            text-align: right;
        }

        /* Spacer */
        .spacer td {
            border: none;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            padding: 3px;
            background: #FFF;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .positive { color: #006600; }
        .negative { color: #CC0000; }

        /* KPI section */
        .kpi-table {
            width: 55%;
            border-collapse: collapse;
            margin: 15px auto 0 auto;
        }

        .kpi-table .kpi-title td {
            background-color: #D3D3D3;
            font-weight: bold;
            font-size: 9px;
            text-align: center;
            padding: 5px;
            border: 1px solid #000;
        }

        .kpi-table .kpi-hdr td {
            background-color: #F5F5F5;
            font-weight: bold;
            font-size: 8px;
            padding: 4px 8px;
            border: 1px solid #000;
        }

        .kpi-table .kpi-data td {
            padding: 4px 8px;
            border: 0.5px solid #999;
            font-size: 9px;
        }

        .kpi-table .kpi-bottom td {
            background-color: #E0E0E0;
            font-weight: bold;
            padding: 5px 8px;
            border: 1px solid #000;
            font-size: 9px;
        }

        /* Signature */
        .sig-section {
            margin-top: 30px;
        }

        .sig-section .sig-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 5px;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }

        .sig-tbl {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .sig-tbl td {
            border: none;
            text-align: center;
            width: 33%;
            padding: 0 10px;
            vertical-align: top;
            font-size: 8px;
        }

        .sig-tbl .s-role {
            font-weight: bold;
            font-size: 8px;
            color: #333;
            text-transform: uppercase;
        }

        .sig-tbl .s-line {
            border-bottom: 1px solid #000;
            margin: 35px 10px 5px 10px;
        }

        .sig-tbl .s-name {
            font-size: 8px;
            font-weight: bold;
        }

        .sig-tbl .s-date {
            font-size: 7px;
            color: #666;
        }
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

        $colCount = count($currencies) + 2;
    @endphp

    <table class="main-table">
        <thead>
            <tr>
                <th style="text-align: left; width: 40%;">Description</th>
                @foreach($currencies as $curr)
                <th style="width: {{ 60 / (count($currencies) + 1) }}%;">{{ $curr }}</th>
                @endforeach
                <th style="width: {{ 60 / (count($currencies) + 1) }}%;">Total USD</th>
            </tr>
        </thead>
        <tbody>

            {{-- REVENUE --}}
            <tr class="section-hdr">
                <td colspan="{{ $colCount }}">REVENUE</td>
            </tr>

            @foreach($revenue as $item)
            <tr class="data-row">
                <td class="lbl">{{ $item['label'] ?? '' }}</td>
                @foreach($currencies as $curr)
                <td class="amt">{{ number_format($item['amounts'][$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="total-col">{{ number_format($item['total_usd'] ?? 0, 2) }}</td>
            </tr>
            @endforeach

            <tr class="sub-total">
                <td>Total Revenue</td>
                @foreach($currencies as $curr)
                <td class="amt">{{ number_format($totalRevenue[$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="total-col">{{ number_format($gRev, 2) }}</td>
            </tr>

            <tr class="spacer"><td colspan="{{ $colCount }}"></td></tr>

            {{-- EXPENSES --}}
            <tr class="section-hdr">
                <td colspan="{{ $colCount }}">OPERATING EXPENSES</td>
            </tr>

            @foreach($expenses as $item)
            <tr class="data-row">
                <td class="lbl">{{ $item['label'] ?? '' }}</td>
                @foreach($currencies as $curr)
                <td class="amt">{{ number_format($item['amounts'][$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="total-col">{{ number_format($item['total_usd'] ?? 0, 2) }}</td>
            </tr>
            @endforeach

            <tr class="sub-total">
                <td>Total Operating Expenses</td>
                @foreach($currencies as $curr)
                <td class="amt">{{ number_format($totalExpenses[$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="total-col">{{ number_format($gExp, 2) }}</td>
            </tr>

            <tr class="spacer"><td colspan="{{ $colCount }}"></td></tr>

            {{-- NET INCOME --}}
            <tr class="net-row">
                <td>NET INCOME</td>
                @foreach($currencies as $curr)
                <td class="amt">{{ number_format($netIncome[$curr] ?? 0, 2) }}</td>
                @endforeach
                <td class="total-col {{ $gNet >= 0 ? 'positive' : 'negative' }}">{{ number_format($gNet, 2) }}</td>
            </tr>

        </tbody>
    </table>

    {{-- KPI --}}
    @php
        $grossMargin = $gRev > 0 ? (($gRev - $gExp) / $gRev) * 100 : 0;
        $netMargin   = $gRev > 0 ? ($gNet / $gRev) * 100 : 0;
        $opRatio     = $gRev > 0 ? ($gExp / $gRev) * 100 : 0;
    @endphp

    <table class="kpi-table">
        <tr class="kpi-title"><td colspan="2">Financial KPI Summary</td></tr>
        <tr class="kpi-hdr">
            <td style="width: 55%;">KPI</td>
            <td class="text-right" style="width: 45%;">Value</td>
        </tr>
        <tr class="kpi-data"><td>Total Revenue (USD)</td><td class="text-right">${{ number_format($gRev, 2) }}</td></tr>
        <tr class="kpi-data"><td>Total Expenses (USD)</td><td class="text-right">${{ number_format($gExp, 2) }}</td></tr>
        <tr class="kpi-data"><td>Gross Profit Margin</td><td class="text-right">{{ number_format($grossMargin, 1) }}%</td></tr>
        <tr class="kpi-data"><td>Net Profit Margin</td><td class="text-right">{{ number_format($netMargin, 1) }}%</td></tr>
        <tr class="kpi-data"><td>Operating Expense Ratio</td><td class="text-right">{{ number_format($opRatio, 1) }}%</td></tr>
        <tr class="kpi-bottom">
            <td>Net Profit (USD)</td>
            <td class="text-right {{ $gNet >= 0 ? 'positive' : 'negative' }}">${{ number_format($gNet, 2) }}</td>
        </tr>
    </table>

    {{-- SIGNATURES --}}
    <div class="sig-section">
        <div class="sig-title">Approval &amp; Signatures</div>
        <table class="sig-tbl">
            <tr>
                <td>
                    <div class="s-role">Prepared By</div>
                    <div class="s-line"></div>
                    <div class="s-name">Finance Officer</div>
                    <div class="s-date">Date: ___/___/______</div>
                </td>
                <td>
                    <div class="s-role">Reviewed By</div>
                    <div class="s-line"></div>
                    <div class="s-name">Accounting Manager</div>
                    <div class="s-date">Date: ___/___/______</div>
                </td>
                <td>
                    <div class="s-role">Approved By</div>
                    <div class="s-line"></div>
                    <div class="s-name">General Manager</div>
                    <div class="s-date">Date: ___/___/______</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
