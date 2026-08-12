<div class="space-y-4">
    @if ($error)
        <div class="rounded-lg bg-danger-50 p-4 text-sm font-medium text-danger-700 dark:bg-danger-950 dark:text-danger-300">
            {{ $error }}
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                <div class="text-xs text-gray-500">Method</div>
                <div class="font-semibold">{{ str($loan->repayment_method)->replace('_', ' ')->title() }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                <div class="text-xs text-gray-500">Installments</div>
                <div class="font-semibold">{{ $summary['installments'] }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                <div class="text-xs text-gray-500">Total Principal</div>
                <div class="font-semibold">{{ number_format($summary['principal'], $loan->currency === 'KHR' ? 0 : 2) }} {{ $loan->currency }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                <div class="text-xs text-gray-500">Principal + Interest + Fee</div>
                <div class="font-semibold">{{ number_format($summary['total'], $loan->currency === 'KHR' ? 0 : 2) }} {{ $loan->currency }}</div>
            </div>
        </div>

        <div class="max-h-96 overflow-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-gray-100 text-left dark:bg-gray-900">
                    <tr>
                        <th class="p-2">No.</th>
                        <th class="p-2">Date</th>
                        <th class="p-2 text-right">Principal</th>
                        <th class="p-2 text-right">Interest</th>
                        <th class="p-2 text-right">Fee</th>
                        <th class="p-2 text-right">Payment</th>
                        <th class="p-2 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($schedule as $row)
                        <tr>
                            <td class="p-2">{{ $row['period'] }}</td>
                            <td class="p-2 whitespace-nowrap">{{ $row['date'] }}</td>
                            <td class="p-2 text-right">{{ number_format($row['principal'], $loan->currency === 'KHR' ? 0 : 2) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['interest'], $loan->currency === 'KHR' ? 0 : 2) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['fee'] ?? 0, $loan->currency === 'KHR' ? 0 : 2) }}</td>
                            <td class="p-2 text-right font-semibold">{{ number_format($row['payment'], $loan->currency === 'KHR' ? 0 : 2) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['balance'] ?? 0, $loan->currency === 'KHR' ? 0 : 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="text-xs text-gray-500">
            First payment: {{ $summary['first_payment_date'] }} · Maturity: {{ $summary['maturity_date'] }}
        </div>
    @endif
</div>
