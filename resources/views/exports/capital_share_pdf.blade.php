<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'khmeros', sans-serif; font-size: 8px; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 3px; text-align: left; vertical-align: middle; word-wrap: break-word; }
        th { background-color: #D3D3D3; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td { background-color: #E0E0E0; font-weight: bold; border-top: 2px double #000; }
        .currency-row td { background-color: #DCEEFF; font-weight: bold; font-size: 9px; }
    </style>
</head>
<body>
    @if(isset($search) && $search)
    <div style="text-align: center; margin-bottom: 10px; font-size: 9px;">
        Search: {{ $search }}
    </div>
    @endif
    
    @php
        $grouped = [];
        foreach ($data as $item) {
            $currency = $item['currency'] ?? 'USD';
            $grouped[$currency][] = $item;
        }
        ksort($grouped);
    @endphp

    @if(empty($grouped))
        <table>
            <tr><td class="text-center">No data available</td></tr>
        </table>
    @endif

    @foreach($grouped as $currency => $items)
    <table>
        <thead>
            <tr class="currency-row">
                <td colspan="14">Currency: {{ $currency }}</td>
            </tr>
            <tr>
                <th width="3%">No.</th>
                <th width="6%">Date</th>
                <th width="8%">Account Code</th>
                <th width="8%">Holder Code</th>
                <th width="12%">Name</th>
                <th width="7%">Type</th>
                <th width="7%">Category</th>
                <th width="5%">Curr.</th>
                <th width="5%">Share</th>
                <th width="8%">Inv. Amount</th>
                <th width="8%">Balance</th>
                <th width="8%">Dividends</th>
                <th width="8%">Div. Paid</th>
                <th width="7%">Last Div.</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalShare = 0; 
                $totalAmount = 0; 
                $totalBalance = 0; 
                $totalDiv = 0; 
                $totalPaid = 0; 
            @endphp
            @foreach($items as $index => $item)
            @php
                $isRealCapital = ($item['category'] ?? '') === 'Real Capital';
                $share = (int)($item['share_qty'] ?? 0);
                $amount = (float)($item['amount'] ?? 0);
                $balance = (float)($item['balance'] ?? 0);
                $div = $isRealCapital ? (float)($item['dividends'] ?? 0) : 0;
                $paid = $isRealCapital ? (float)($item['total_dividend_paid'] ?? 0) : 0;
                
                $totalShare += $share;
                $totalAmount += $amount;
                $totalBalance += $balance;
                $totalDiv += $div;
                $totalPaid += $paid;

                $date = $item['borrowing_date'] ?? $item['created_at'] ?? '';
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ !empty($date) && $date !== '-' ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '' }}</td>
                <td>{{ $item['account_no'] ?? '' }}</td>
                <td>{{ $item['lender_code'] ?? '' }}</td>
                <td>{{ $item['investor_name'] ?? $item['lender_name'] ?? '' }}</td>
                <td>{{ $item['lender_type'] ?? '' }}</td>
                <td>{{ $item['category'] ?? '' }}</td>
                <td class="text-center">{{ $currency }}</td>
                <td class="text-center">{{ $share }}</td>
                <td class="text-right">{{ number_format($amount, 2) }}</td>
                <td class="text-right">{{ number_format($balance, 2) }}</td>
                <td class="text-right">{{ $isRealCapital ? number_format($div, 2) : '-' }}</td>
                <td class="text-right">{{ $isRealCapital ? number_format($paid, 2) : '-' }}</td>
                <td class="text-center">{{ !empty($item['last_dividend_date']) && $item['last_dividend_date'] !== '-' ? \Carbon\Carbon::parse($item['last_dividend_date'])->format('d/m/Y') : '' }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="8" class="text-center">Total</td>
                <td class="text-center">{{ $totalShare }}</td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
                <td class="text-right">{{ number_format($totalBalance, 2) }}</td>
                <td class="text-right">{{ number_format($totalDiv, 2) }}</td>
                <td class="text-right">{{ number_format($totalPaid, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endforeach
</body>
</html>
