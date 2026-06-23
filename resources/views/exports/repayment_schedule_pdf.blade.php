<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Repay</title>
    <style>
        body {
            font-family: '{{ $font }}', sans-serif;
            font-size: 8px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: fixed;
        }
        th, td {
            border: 0.3px solid #666;
            padding: 4px;
            text-align: right;
            word-wrap: break-word;
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
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .summary-table {
            margin-top: 10px;
            width: 50%;
            float: right;
        }
        .summary-table th {
            background-color: #f5f5f5;
        }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-warning { color: #ffc107; }
        
        /* Specific column widths for better fit in landscape */
        .col-no { width: 3%; }
        .col-date { width: 6%; }
        .col-code { width: 6%; }
        .col-name { width: 7%; }
        .col-phone { width: 6%; }
        .col-address { width: 6%; }
        .col-currency { width: 4%; }
        .col-amount { width: 6%; }
    </style>
</head>
<body>

    <table>
        <thead>
            <tr>
                <th class="col-no">No.</th>
                <th class="col-date">Payment Date</th>
                <th class="col-code">Loan Code</th>
                <th class="col-name">Client Name</th>
                <th class="col-phone">Phone</th>
                <th class="col-address">Village</th>
                <th class="col-address">Commune</th>
                <th class="col-address">District</th>
                <th class="col-address">Province</th>
                <th class="col-currency">Currency</th>
                <th class="col-amount">Installment</th>
                <th class="col-amount">Loan Amount</th>
                <th class="col-amount">OS</th>
                <th class="col-amount">Principal</th>
                <th class="col-amount">Interest</th>
                <th class="col-amount">Total</th>
                <th class="col-amount">Total Paid</th>
                <th class="col-amount">Remaining</th>
                <th class="col-amount">Status</th>
                <th class="col-name">Credit Officer</th>
            </tr>
        </thead>
        <tbody>
            @php
                $no = 1;
                $totalsUSD = [
                    'loan_amount' => 0,
                    'outstanding_balance' => 0,
                    'principal_amount' => 0,
                    'interest_amount' => 0,
                    'total_due' => 0,
                    'total_paid' => 0,
                    'remaining' => 0,
                ];
                $totalsKHR = [
                    'loan_amount' => 0,
                    'outstanding_balance' => 0,
                    'principal_amount' => 0,
                    'interest_amount' => 0,
                    'total_due' => 0,
                    'total_paid' => 0,
                    'remaining' => 0,
                ];
            @endphp
            @foreach ($data as $item)
                @php
                    $currency = explode(' ', $item->currency ?? 'USD')[0];
                    if (strtoupper($currency) === 'KHR') {
                        $totalsKHR['loan_amount'] += $item->loan_amount;
                        $totalsKHR['outstanding_balance'] += $item->outstanding_balance;
                        $totalsKHR['principal_amount'] += $item->principal_amount;
                        $totalsKHR['interest_amount'] += $item->interest_amount;
                        $totalsKHR['total_due'] += $item->total_due;
                        $totalsKHR['total_paid'] += $item->total_paid;
                        $totalsKHR['remaining'] += $item->remaining;
                    } else {
                        $totalsUSD['loan_amount'] += $item->loan_amount;
                        $totalsUSD['outstanding_balance'] += $item->outstanding_balance;
                        $totalsUSD['principal_amount'] += $item->principal_amount;
                        $totalsUSD['interest_amount'] += $item->interest_amount;
                        $totalsUSD['total_due'] += $item->total_due;
                        $totalsUSD['total_paid'] += $item->total_paid;
                        $totalsUSD['remaining'] += $item->remaining;
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $no++ }}</td>
                    <td class="text-center">{{ $item->payment_date ? \Carbon\Carbon::parse($item->payment_date)->format('d/m/Y') : '-' }}</td>
                    <td class="text-left">{{ $item->loan_code ?? '-' }}</td>
                    <td class="text-left">{{ trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')) ?: '-' }}</td>
                    <td class="text-left">{{ $item->phone ?? '-' }}</td>
                    <td class="text-left">{{ $item->village ?? '-' }}</td>
                    <td class="text-left">{{ $item->commune ?? '-' }}</td>
                    <td class="text-left">{{ $item->district ?? '-' }}</td>
                    <td class="text-left">{{ $item->province ?? '-' }}</td>
                    <td class="text-center">{{ $currency }}</td>
                    <td class="text-center">{{ $item->installment_display ?? '-' }}</td>
                    <td>{{ number_format($item->loan_amount, 2) }}</td>
                    <td>{{ number_format($item->outstanding_balance, 2) }}</td>
                    <td>{{ number_format($item->principal_amount, 2) }}</td>
                    <td>{{ number_format($item->interest_amount, 2) }}</td>
                    <td>{{ number_format($item->total_due, 2) }}</td>
                    <td>{{ number_format($item->total_paid, 2) }}</td>
                    <td>{{ number_format($item->remaining, 2) }}</td>
                    <td class="text-center">{{ ucfirst($item->payment_status ?? 'Active') }}</td>
                    <td class="text-left">{{ $item->officer_name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-bold" style="background-color: #e0e0e0;">
                <td colspan="11" class="text-right">Total</td>
                <td>USD {{ number_format($totalsUSD['loan_amount'], 2) }}<br>KHR {{ number_format($totalsKHR['loan_amount'], 0) }}</td>
                <td>USD {{ number_format($totalsUSD['outstanding_balance'], 2) }}<br>KHR {{ number_format($totalsKHR['outstanding_balance'], 0) }}</td>
                <td>USD {{ number_format($totalsUSD['principal_amount'], 2) }}<br>KHR {{ number_format($totalsKHR['principal_amount'], 0) }}</td>
                <td>USD {{ number_format($totalsUSD['interest_amount'], 2) }}<br>KHR {{ number_format($totalsKHR['interest_amount'], 0) }}</td>
                <td>USD {{ number_format($totalsUSD['total_due'], 2) }}<br>KHR {{ number_format($totalsKHR['total_due'], 0) }}</td>
                <td>USD {{ number_format($totalsUSD['total_paid'], 2) }}<br>KHR {{ number_format($totalsKHR['total_paid'], 0) }}</td>
                <td>USD {{ number_format($totalsUSD['remaining'], 2) }}<br>KHR {{ number_format($totalsKHR['remaining'], 0) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
