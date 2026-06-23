<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body {
            font-family: 'khmeros', sans-serif;
            font-size: 8px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 0.3px solid #666;
            padding: 4px;
            text-align: right;
        }
        th {
            background-color: #d3d3d3;
            color: #000000;
            font-weight: bold;
            text-align: center;
            font-size: 8px;
        }
        .header-group th {
            border-bottom: 0.3px solid #aaa;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .bg-light { background-color: #eaf0f9; }
    </style>
</head>
<body>

    <table>
        <thead>
            <tr class="header-group">
                <th rowspan="2" width="3%">No</th>
                <th colspan="3">Loan Outstanding</th>
                <th colspan="3">PAR $</th>
                <th>TOTAL</th>
                <th>PAR NPL</th>
                <th>PAR %</th>
                <th colspan="4">Loan Count</th>
            </tr>
            <tr>
                <!-- Loan Outstanding -->
                <th width="7.5%">USD</th>
                <th width="7.5%">KHR (in$)</th>
                <th width="7.5%">Total USD</th>
                <!-- PAR $ -->
                <th width="7.5%">USD</th>
                <th width="7.5%">KHR (in$)</th>
                <th width="7.5%">Total USD</th>
                <!-- PAR % -->
                <th width="6%">PAR%</th>
                <!-- PAR NPL -->
                <th width="7.5%">Total USD</th>
                <!-- PAR NPL % -->
                <th width="6%">NPL</th>
                <!-- Loan Count -->
                <th width="5.5%">#Active<br>Loan</th>
                <th width="5.5%">#PAR1</th>
                <th width="5.5%">#PAR&lt;=30</th>
                <th width="5.5%">#PAR&gt;30</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumUsdOs = 0;
                $sumKhrOs = 0;
                $sumTotalOs = 0;
                $sumParUsd = 0;
                $sumParKhr = 0;
                $sumParTotal = 0;
                $sumNpl = 0;
                $sumActive = 0;
                $sumPar1 = 0;
                $sumPar2To30 = 0;
                $sumPar30Plus = 0;
            @endphp
            @foreach($data as $index => $item)
                @php
                    $sumUsdOs += (float) ($item['usd_loan_os'] ?? 0);
                    $sumKhrOs += (float) ($item['khr_loan_os'] ?? 0);
                    $sumTotalOs += (float) ($item['total_loan_os'] ?? 0);
                    $sumParUsd += (float) ($item['par_usd_amount'] ?? 0);
                    $sumParKhr += (float) ($item['par_khr_amount'] ?? 0);
                    $sumParTotal += (float) ($item['par_total_amount'] ?? 0);
                    $sumNpl += (float) ($item['npl_amount'] ?? 0);
                    $sumActive += (int) ($item['active_loan_count'] ?? 0);
                    $sumPar1 += (int) ($item['par1_count'] ?? 0);
                    $sumPar2To30 += (int) ($item['par_lte_30_count'] ?? 0);
                    $sumPar30Plus += (int) ($item['par_gt_30_count'] ?? 0);
                @endphp
                <tr>
                    <td class="text-left">{{ $index + 1 }}</td>
                    <td>{{ number_format((float) ($item['usd_loan_os'] ?? 0), 2) }}</td>
                    <td>{{ number_format((float) ($item['khr_loan_os'] ?? 0), 2) }}</td>
                    <td>{{ number_format((float) ($item['total_loan_os'] ?? 0), 2) }}</td>
                    
                    <td>{{ number_format((float) ($item['par_usd_amount'] ?? 0), 2) }}</td>
                    <td>{{ number_format((float) ($item['par_khr_amount'] ?? 0), 2) }}</td>
                    <td>{{ number_format((float) ($item['par_total_amount'] ?? 0), 2) }}</td>
                    
                    <td class="text-center">{{ number_format((float) ($item['par_percent'] ?? 0), 2) }}%</td>
                    
                    <td>{{ number_format((float) ($item['npl_amount'] ?? 0), 2) }}</td>
                    
                    <td class="text-center">{{ number_format((float) ($item['npl_percent'] ?? 0), 2) }}%</td>
                    
                    <td class="text-center">{{ (int) ($item['active_loan_count'] ?? 0) ?: '-' }}</td>
                    <td class="text-center">{{ (int) ($item['par1_count'] ?? 0) ?: '-' }}</td>
                    <td class="text-center">{{ (int) ($item['par_lte_30_count'] ?? 0) ?: '-' }}</td>
                    <td class="text-center">{{ (int) ($item['par_gt_30_count'] ?? 0) ?: '-' }}</td>
                </tr>
            @endforeach
            
            @php
                $totalParPercent = $sumTotalOs > 0 ? ($sumParTotal / $sumTotalOs) * 100 : 0.0;
                $totalNplPercent = $sumTotalOs > 0 ? ($sumNpl / $sumTotalOs) * 100 : 0.0;
            @endphp
            
            <tr class="bg-light font-bold">
                <td class="text-center font-bold">សរុប</td>
                <td class="font-bold">{{ number_format($sumUsdOs, 2) }}</td>
                <td class="font-bold">{{ number_format($sumKhrOs, 2) }}</td>
                <td class="font-bold">{{ number_format($sumTotalOs, 2) }}</td>
                
                <td class="font-bold">{{ number_format($sumParUsd, 2) }}</td>
                <td class="font-bold">{{ number_format($sumParKhr, 2) }}</td>
                <td class="font-bold">{{ number_format($sumParTotal, 2) }}</td>
                
                <td class="text-center font-bold">{{ number_format($totalParPercent, 2) }}%</td>
                
                <td class="font-bold">{{ number_format($sumNpl, 2) }}</td>
                
                <td class="text-center font-bold">{{ number_format($totalNplPercent, 2) }}%</td>
                
                <td class="text-center font-bold">{{ $sumActive ?: '-' }}</td>
                <td class="text-center font-bold">{{ $sumPar1 ?: '-' }}</td>
                <td class="text-center font-bold">{{ $sumPar2To30 ?: '-' }}</td>
                <td class="text-center font-bold">{{ $sumPar30Plus ?: '-' }}</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
