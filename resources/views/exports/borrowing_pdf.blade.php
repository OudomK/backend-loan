<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'khmeros', sans-serif; font-size: 9px; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: left; vertical-align: middle; }
        th { background-color: #D3D3D3; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td { background-color: #E0E0E0; font-weight: bold; border-top: 2px double #000; }
        .no-border { border: none !important; }
    </style>
</head>
<body>
    @if(isset($search) && $search)
    <div style="text-align: center; margin-bottom: 10px; font-size: 10px;">
        Search: {{ $search }}
    </div>
    @endif
    
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Date</th>
                <th>Account Code</th>
                <th>Lender Code</th>
                <th>Name</th>
                <th>Type</th>
                <th>Pay. Method</th>
                <th>Curr.</th>
                <th>Term</th>
                <th>Amount</th>
                <th>Int. Rate</th>
                <th>Fee</th>
                <th>Maturity</th>
                <th>S/L Term</th>
                <th>Balance</th>
                <th>Late Prin.</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalAmount = 0; 
                $totalFee = 0; 
                $totalBalance = 0; 
                $totalLatePrin = 0; 
            @endphp
            @foreach($data as $index => $item)
            @php
                $amount = (float)($item['amount'] ?? 0);
                $fee = (float)($item['fee'] ?? 0);
                $balance = (float)($item['balance'] ?? 0);
                $latePrin = (float)($item['late_principal'] ?? 0);
                
                $totalAmount += $amount;
                $totalFee += $fee;
                $totalBalance += $balance;
                $totalLatePrin += $latePrin;
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ !empty($item['borrowing_date']) && $item['borrowing_date'] !== '-' ? \Carbon\Carbon::parse($item['borrowing_date'])->format('d/m/Y') : '' }}</td>
                <td>{{ $item['account_no'] ?? '' }}</td>
                <td>{{ $item['lender_code'] ?? '' }}</td>
                <td>{{ $item['lender_name'] ?? '' }}</td>
                <td>{{ $item['lender_type'] ?? '' }}</td>
                <td>{{ $item['payment_method'] ?? '' }}</td>
                <td>{{ $item['currency'] ?? '' }}</td>
                <td class="text-center">{{ $item['term_months'] ?? '' }}</td>
                <td class="text-right">{{ number_format($amount, 2) }}</td>
                <td class="text-right">{{ number_format((float)($item['interest_rate'] ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format($fee, 2) }}</td>
                <td>{{ !empty($item['maturity_date']) && $item['maturity_date'] !== '-' ? \Carbon\Carbon::parse($item['maturity_date'])->format('d/m/Y') : '' }}</td>
                <td>{{ $item['sl_term'] ?? '' }}</td>
                <td class="text-right">{{ number_format($balance, 2) }}</td>
                <td class="text-right">{{ number_format($latePrin, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="8" class="text-center">Total</td>
                <td></td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
                <td></td>
                <td class="text-right">{{ number_format($totalFee, 2) }}</td>
                <td></td>
                <td></td>
                <td class="text-right">{{ number_format($totalBalance, 2) }}</td>
                <td class="text-right">{{ number_format($totalLatePrin, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
