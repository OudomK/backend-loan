<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repayment Schedule</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background-color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .page {
                box-shadow: none;
                margin: 0 auto;
            }

            .hide-on-print {
                display: none !important;
            }
        }
    </style>
</head>

<body class="flex justify-center py-8 print:p-0 relative min-h-screen" style="background-color: #1f2937;">

    <div class="absolute top-4 left-4 hide-on-print">
        <a href="javascript:history.back()"
            class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg shadow hover:bg-gray-50 flex items-center gap-2"
            style="display: flex; align-items: center; gap: 8px; font-family: 'Krasar', 'Battambang', 'Khmer OS Battambang', sans-serif;">
            <svg xmlns="http://www.w3.org/2000/svg" style="height: 16px; width: 16px;" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            ត្រឡប់ក្រោយ
        </a>
    </div>

    <div class="hide-on-print" style="position: absolute; top: 16px; right: 16px;" id="save-pdf-btn-container">
        <button onclick="generatePDF()"
            style="background-color: #2563eb; color: white; padding: 8px 16px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px; border: none; cursor: pointer; font-family: inherit; font-size: 15px;">
            <svg xmlns="http://www.w3.org/2000/svg" style="height: 18px; width: 18px;" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Save as PDF
        </button>
    </div>

    <div class="page bg-white shadow-lg print:shadow-none"
        style="width: 794px; margin: 0 auto; padding: 5mm 15mm 15mm; box-sizing: border-box;">

        <!-- PRINTABLE A4 CONTENT -->
        <div class="w-full text-black"
            style="font-family: 'Krasar', 'Battambang', 'Khmer OS Battambang', sans-serif; font-size: 11px;">

            <!-- Header -->
            <div class="flex items-center justify-between mb-2">
                <div style="width: 120px;">
                    <img src="{{ asset('images/logo.jpg') }}" alt="LOGO"
                        style="width: 65px; height: auto; object-fit: contain;"
                        data-fallback="{{ asset('images/light_logo.png') }}"
                        onerror="this.onerror=null; this.src=this.dataset.fallback;">
                </div>
                <div class="flex-1 text-center">
                    <h1 class="text-black m-0 leading-tight font-bold"
                        style="font-size: 18px; font-family: 'Krasar', 'Battambang', sans-serif;">តារាងកាលវិភាគបង់ប្រាក់
                    </h1>

                </div>
                <div style="width: 120px;"></div>
            </div>

            <div style="border-bottom: 2px solid #000; margin-top: 4px; margin-bottom: 8px;"></div>

            <!-- Customer Info Grid -->
            @if($customer_info)
                @php
                    $khmerNums = ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩'];
                    $khmerMonths = ['Jan' => 'មករា', 'Feb' => 'កុម្ភៈ', 'Mar' => 'មិនា', 'Apr' => 'មេសា', 'May' => 'ឧសភា', 'Jun' => 'មិថុនា', 'Jul' => 'កក្កដា', 'Aug' => 'សីហា', 'Sep' => 'កញ្ញា', 'Oct' => 'តុលា', 'Nov' => 'វិច្ឆិកា', 'Dec' => 'ធ្នូ'];
                @endphp
                <table class="w-full mb-[8px] border-collapse" style="width: 100%; table-layout: fixed;">
                    <tbody>
                        <tr>
                            <!-- Row 1 -->
                            <td class="align-middle border-none" style="padding-bottom: 10px; width: 36%;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 110px; display: inline-block;">លេខកិច្ចសន្យា៖</span>
                                    <span
                                        class="text-[11px] font-bold whitespace-nowrap leading-tight">{{ $customer_info['customer_id'] ?: 'QF-001' }}</span>
                                </div>
                            </td>
                            <td class="align-middle border-none" style="padding-bottom: 10px; width: 36%;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 110px; display: inline-block;">កាលបរិច្ឆេទខ្ចី៖</span>
                                    <span class="text-[11px] font-bold whitespace-nowrap leading-tight">
                                        @php
                                            try {
                                                $dDate = \Carbon\Carbon::parse(str_replace('/', '-', $customer_info['loan_date'] ?: now()));
                                                $khDate = strtr($dDate->format('d'), $khmerNums) . '-' . ($khmerMonths[$dDate->format('M')] ?? $dDate->format('M')) . '-' . strtr($dDate->format('Y'), $khmerNums);
                                                echo $khDate;
                                            } catch (\Exception $e) {
                                                echo $customer_info['loan_date'];
                                            }
                                        @endphp
                                    </span>
                                </div>
                            </td>
                            <td class="align-middle border-none" style="padding-bottom: 10px; width: 28%;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 80px; display: inline-block;">រយៈពេល៖</span>
                                    <span class="text-[11px] font-bold whitespace-nowrap leading-tight">
                                        {{ strtr(str_replace(['Months', 'Days', 'Years'], [' ខែ', ' ថ្ងៃ', ' ឆ្នាំ'], $customer_info['duration']), $khmerNums) }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <!-- Row 2 -->
                            <td class="align-middle border-none" style="padding-bottom: 10px;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 110px; display: inline-block;">ឈ្មោះអតិថិជន៖</span>
                                    <span
                                        class="text-[11px] font-bold whitespace-nowrap leading-tight">{{ $customer_info['customer_name'] ?: '-' }}</span>
                                </div>
                            </td>
                            <td class="align-middle border-none" style="padding-bottom: 10px;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 110px; display: inline-block;">ចំនួនទឹកប្រាក់៖</span>
                                    <span
                                        class="text-[11px] font-bold whitespace-nowrap leading-tight">{{ str_replace('.00', '', number_format((float) $customer_info['amount'], 2)) }}</span>
                                </div>
                            </td>
                            <td class="align-middle border-none" style="padding-bottom: 10px;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 80px; display: inline-block;">រូបិយប័ណ្ណ៖</span>
                                    <span class="text-[11px] font-bold whitespace-nowrap leading-tight">
                                        {{ $customer_info['currency'] === 'KHR' ? 'ប្រាក់រៀល' : ($customer_info['currency'] === 'USD' ? 'ប្រាក់ដុល្លារ' : $customer_info['currency']) }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <!-- Row 3 -->
                            <td class="align-middle border-none" style="padding-bottom: 10px;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 110px; display: inline-block;">ភេទ៖</span>
                                    <span
                                        class="text-[11px] font-bold whitespace-nowrap leading-tight">{{ $customer_info['gender'] === 'Male' ? 'ប្រុស' : ($customer_info['gender'] === 'Female' ? 'ស្រី' : '-') }}</span>
                                </div>
                            </td>
                            <td class="align-middle border-none" style="padding-bottom: 10px;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 110px; display: inline-block;">លេខអត្តសញ្ញាណ៖</span>
                                    <span
                                        class="text-[11px] font-bold whitespace-nowrap leading-tight">{{ strtr($customer_info['id_number'] ?: '-', $khmerNums) }}</span>
                                </div>
                            </td>
                            <td class="align-middle border-none" style="padding-bottom: 10px;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 80px; display: inline-block;">វគ្គទី៖</span>
                                    <span
                                        class="text-[11px] font-bold whitespace-nowrap leading-tight">{{ strtr($customer_info['cycle'] ?: '-', $khmerNums) }}</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <!-- Row 4 -->
                            <td class="align-top border-none" style="padding-bottom: 10px;">
                                <div class="flex items-start">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 110px; display: inline-block; flex-shrink: 0;">អាសយដ្ឋាន៖</span>
                                    <span
                                        class="text-[11px] font-bold leading-tight flex-1">{{ $customer_info['address'] ?: '-' }}</span>
                                </div>
                            </td>
                            <td class="align-middle border-none" style="padding-bottom: 10px;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 110px; display: inline-block;">សងលើកដំបូង៖</span>
                                    <span class="text-[11px] font-bold whitespace-nowrap leading-tight">
                                        @php
                                            if (!empty($customer_info['first_repayment_date'])) {
                                                try {
                                                    $dDate2 = \Carbon\Carbon::parse(str_replace('/', '-', $customer_info['first_repayment_date']));
                                                    echo strtr($dDate2->format('d'), $khmerNums) . '-' . ($khmerMonths[$dDate2->format('M')] ?? $dDate2->format('M')) . '-' . strtr($dDate2->format('Y'), $khmerNums);
                                                } catch (\Exception $e) {
                                                    echo $customer_info['first_repayment_date'];
                                                }
                                            } else {
                                                echo '-';
                                            }
                                        @endphp
                                    </span>
                                </div>
                            </td>
                            <td class="align-middle border-none" style="padding-bottom: 10px;">
                                <div class="flex items-baseline">
                                    <span class="text-[11px] leading-tight"
                                        style="width: 80px; display: inline-block;">លេខទូរស័ព្ទ៖</span>
                                    <span
                                        class="text-[11px] font-bold whitespace-nowrap leading-tight">{{ $customer_info['phone_number'] ?: '-' }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            @endif

            <!-- Schedule Table -->
            @php
                $isSplitMethod = in_array($customer_info['repayment_method'] ?? '', ['fixed_15days_70_30', 'fixed_15days_50_50']);
                $khmerNums = ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩'];
                $day1Kh = '១១';
                $day2Kh = '២៦';
                if ($isSplitMethod && count($schedule) > 0) {
                    $uniqueDays = [];
                    foreach ($schedule as $item) {
                        $uniqueDays[] = (int) substr($item['date'], 0, 2);
                    }
                    $uniqueDays = array_unique($uniqueDays);
                    sort($uniqueDays);
                    $day1 = count($uniqueDays) > 0 ? $uniqueDays[0] : 11;
                    $day2 = count($uniqueDays) > 1 ? $uniqueDays[1] : 26;

                    $day1Kh = strtr($day1, $khmerNums);
                    $day2Kh = strtr($day2, $khmerNums);

                    $groupedByMonth = [];
                    foreach ($schedule as $item) {
                        $m = substr($item['date'], 3, 7);
                        if (!isset($groupedByMonth[$m]))
                            $groupedByMonth[$m] = [];
                        $groupedByMonth[$m][] = $item;
                    }
                }

                $khmerDays = [
                    0 => 'អាទិត្យ',
                    1 => 'ចន្ទ',
                    2 => 'អង្គារ',
                    3 => 'ពុធ',
                    4 => 'ព្រហស្បតិ៍',
                    5 => 'សុក្រ',
                    6 => 'សៅរ៍'
                ];
                $currencyCode = $customer_info['currency'] ?? 'USD';
                if ($currencyCode === 'KHR') {
                    $currSymbol = '៛';
                    $isNoDecimal = true;
                    $symbolInFront = false;
                } else {
                    $currSymbol = '$';
                    $isNoDecimal = false;
                    $symbolInFront = true;
                }

                $formatMoney = function ($amount) use ($isNoDecimal, $currSymbol, $symbolInFront) {
                    if ($amount === null || $amount === '')
                        return '-';
                    $val = str_replace('.00', '', number_format((float) $amount, $isNoDecimal ? 0 : 2));
                    return $symbolInFront ? $currSymbol . ' ' . $val : $val . ' ' . $currSymbol;
                };
            @endphp

            @if($isSplitMethod)
                <table class="w-full border-collapse mt-[4px] table-fixed" style="width: 100%; table-layout: fixed;">
                    <thead class="bg-[#d7ffff] text-black"
                        style="-webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #d7ffff;">
                        <tr>
                            <th rowspan="2" scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 6%; height: 56px; border: 1px solid #111; text-align: center;">ល.រ</th>
                            <th rowspan="2" scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 22%; border: 1px solid #111; text-align: center;">ថ្ងៃសងប្រាក់</th>
                            <th rowspan="2" scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 17.5%; border: 1px solid #111; text-align: center;">ប្រាក់ដើម</th>
                            <th colspan="2" scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 24.5%; border: 1px solid #111; text-align: center;">សរុបទឹកប្រាក់បង់</th>
                            <th rowspan="2" scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 17.5%; border: 1px solid #111; text-align: center;">សមតុល្យប្រាក់ដើម</th>
                            <th rowspan="2" scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 12.5%; border: 1px solid #111; text-align: center;">ផ្សេងៗ</th>
                        </tr>
                        <tr>
                            <th scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 12.25%; border: 1px solid #111; text-align: center;">បង់ថ្ងៃទី <span
                                    class="text-red-600"
                                    style="color: red; text-decoration: underline;">{{ $day1Kh }}</span></th>
                            <th scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 12.25%; border: 1px solid #111; text-align: center;">បង់ថ្ងៃទី <span
                                    class="text-red-600"
                                    style="color: red; text-decoration: underline;">{{ $day2Kh }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($groupedByMonth))
                            @php $index = 0; @endphp
                            @foreach($groupedByMonth as $month => $items)
                                @php
                                    $payment1 = null;
                                    $payment2 = null;
                                    foreach ($items as $i) {
                                        $d = (int) substr($i['date'], 0, 2);
                                        if ($d == $day1)
                                            $payment1 = $i;
                                        else
                                            $payment2 = $i;
                                    }

                                    $displayItem = $payment1 ?: $payment2;
                                    $dayOfWeekKh = '';
                                    try {
                                        $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $displayItem['date']);
                                        $formattedDate = $dateObj->format('d/m/Y');
                                        $dayOfWeekKh = $khmerDays[$dateObj->dayOfWeek] ?? '';
                                    } catch (\Exception $e) {
                                        $formattedDate = $displayItem['date'];
                                    }

                                    $monthPrincipal = ($payment1 ? $payment1['principal'] : 0) + ($payment2 ? $payment2['principal'] : 0);
                                    $balance = $payment2 ? $payment2['balance'] : ($payment1 ? $payment1['balance'] : 0);
                                @endphp
                                <tr>
                                    <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                        style="border: 1px solid #111; text-align: center; height: 24px;">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                        style="border: 1px solid #111; text-align: center; height: 24px;">
                                        <div style="display: flex; justify-content: center; gap: 8px;">
                                            <span style="width: 65px; text-align: right;">{{ $formattedDate }}</span>
                                            <span style="width: 45px; text-align: left;">{{ $dayOfWeekKh }}</span>
                                        </div>
                                    </td>
                                    <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                        style="border: 1px solid #111; text-align: center; height: 24px;">
                                        {{ $formatMoney($monthPrincipal) }}
                                    </td>
                                    <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                        style="border: 1px solid #111; text-align: center; height: 24px;">
                                        {{ $formatMoney($payment1 ? $payment1['payment'] : 0) }}
                                    </td>
                                    <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                        style="border: 1px solid #111; text-align: center; height: 24px;">
                                        {{ $formatMoney($payment2 ? $payment2['payment'] : 0) }}
                                    </td>
                                    <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                        style="border: 1px solid #111; text-align: center; height: 24px;">
                                        {{ $formatMoney($balance) }}
                                    </td>
                                    <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                        style="border: 1px solid #111; text-align: center; height: 24px;">

                                    </td>
                                </tr>
                                @php $index++; @endphp
                            @endforeach
                        @endif

                        <!-- Total Row -->
                        <tr>
                            <td class="border-none" style="border: none;"></td>
                            <td class="border-none text-right pr-[8px] text-[11px]"
                                style="border: none; padding-right: 8px; text-align: right;">សរុប:</td>
                            <td class="border border-[#111] bg-white p-[5px_4px] text-center text-[10px]"
                                style="border: 1px solid #111; background-color: white; text-align: center;">
                                {{ $formatMoney(collect($schedule)->sum('principal')) }}
                            </td>
                            <td class="border-none" style="border: none;"></td>
                            <td class="border-none" style="border: none;"></td>
                            <td class="border-none" style="border: none;"></td>
                            <td class="border-none" style="border: none;"></td>
                        </tr>
                    </tbody>
                </table>
            @else
                <table class="w-full border-collapse mt-[4px] table-fixed" style="width: 100%; table-layout: fixed;">
                    <thead class="bg-[#d7ffff] text-black h-[56px]"
                        style="-webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #d7ffff;">
                        <tr>
                            <th scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 8%; height: 56px; border: 1px solid #111; text-align: center;">ល.រ</th>
                            <th scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 22%; border: 1px solid #111; text-align: center;">ថ្ងៃសងប្រាក់</th>
                            <th scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 17.5%; border: 1px solid #111; text-align: center;">ប្រាក់ដើម</th>
                            <th scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 17.5%; border: 1px solid #111; text-align: center;">សរុបទឹកប្រាក់បង់</th>
                            <th scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 20%; border: 1px solid #111; text-align: center;">សមតុល្យប្រាក់ដើម</th>
                            <th scope="col"
                                class="border border-[#111] p-[5px_4px] text-center text-[11px] font-bold"
                                style="width: 15%; border: 1px solid #111; text-align: center;">ផ្សេងៗ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedule as $item)
                            <tr>
                                <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                    style="border: 1px solid #111; text-align: center; height: 24px;">
                                    {{ $item['installment_no'] ?? $loop->iteration }}
                                </td>
                                <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                    style="border: 1px solid #111; text-align: center; height: 24px;">
                                    <div style="display: flex; justify-content: center; gap: 8px;">
                                        @php
                                            $dayOfWeekKh = '';
                                            try {
                                                $dateObj = \Carbon\Carbon::parse(str_replace('/', '-', $item['date']));
                                                $formattedDate = $dateObj->format('d/m/Y');
                                                $dayOfWeekKh = $khmerDays[$dateObj->dayOfWeek] ?? '';
                                            } catch (\Exception $e) {
                                                try {
                                                    $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $item['date']);
                                                    $formattedDate = $dateObj->format('d/m/Y');
                                                    $dayOfWeekKh = $khmerDays[$dateObj->dayOfWeek] ?? '';
                                                } catch (\Exception $e2) {
                                                    $formattedDate = $item['date'];
                                                }
                                            }
                                        @endphp
                                        <span style="width: 65px; text-align: right;">{{ $formattedDate }}</span>
                                        <span style="width: 45px; text-align: left;">{{ $dayOfWeekKh }}</span>
                                    </div>
                                </td>
                                <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                    style="border: 1px solid #111; text-align: center; height: 24px;">
                                    {{ $formatMoney($item['principal']) }}
                                </td>
                                <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                    style="border: 1px solid #111; text-align: center; height: 24px;">
                                    {{ $formatMoney($item['payment']) }}
                                </td>
                                <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                    style="border: 1px solid #111; text-align: center; height: 24px;">
                                    {{ $formatMoney($item['balance']) }}
                                </td>
                                <td class="border border-[#111] p-[5px_4px] text-center text-[11px] whitespace-nowrap"
                                    style="border: 1px solid #111; text-align: center; height: 24px;">

                                </td>
                            </tr>
                        @endforeach

                        <!-- Total Row -->
                        <tr>
                            <td class="border-none" style="border: none;"></td>
                            <td class="border-none text-right pr-[8px] text-[11px]"
                                style="border: none; padding-right: 8px; text-align: right;">សរុប:</td>
                            <td class="border border-[#111] bg-white p-[5px_4px] text-center text-[11px]"
                                style="border: 1px solid #111; background-color: white; text-align: center;">
                                {{ $formatMoney(collect($schedule)->sum('principal')) }}
                            </td>
                            <td class="border-none" style="border: none;"></td>
                            <td class="border-none" style="border: none;"></td>
                            <td class="border-none" style="border: none;"></td>
                        </tr>
                    </tbody>
                </table>
            @endif

            <!-- Footer Signatures -->
            <div class="flex justify-between items-end" style="margin-top: 14px;">
                <div class="w-[170px] text-center">
                    <div class="text-[13px]" style="margin-bottom: 100px;">ហត្ថលេខាគណនេយ្យករ</div>
                    <div style="border-bottom: 1px solid #000;"></div>
                </div>

                @php
                    $qrImage = !empty($customer_info['qr_image_url']) ? $customer_info['qr_image_url'] : null;
                @endphp
                <div class="flex items-center justify-center"
                    style="width: 104px; height: 104px; overflow: hidden; padding: 2px;">
                    @if($qrImage)
                        <img src="{{ asset($qrImage) }}" alt="QR Code"
                            style="width: 100%; height: 100%; object-fit: contain;">
                    @else
                        <span class="text-[11px] text-gray-500">No QR</span>
                    @endif
                </div>

                <div class="w-[170px] text-center">
                    <div class="text-[13px]" style="margin-bottom: 100px;">ស្នាមមេដៃកូនបំណុល</div>
                    <div style="border-bottom: 1px solid #000;"></div>
                </div>
            </div>

            <!-- Terms and Conditions Text -->
            <div style="margin-top: 28px; font-family: 'Krasar', 'Battambang', 'Khmer OS Battambang', sans-serif;">
                <p style="font-size: 11px; line-height: 2; text-align: justify; margin: 0; text-indent: 40px;">បន្ទាប់ពីបានអាន និងស្តាប់នូវរាល់ខ្លឹមសារនៃកិច្ចសន្យាខ្ចីប្រាក់ថ្ងៃទី@php
                    $loanDate = $customer_info['loan_date'] ?? null;
                    if ($loanDate) {
                        try {
                            $parsedDate = \Carbon\Carbon::parse(str_replace('/', '-', $loanDate));
                            $day = strtr($parsedDate->format('d'), $khmerNums);
                            $month = $khmerMonths[$parsedDate->format('M')] ?? strtr($parsedDate->format('m'), $khmerNums);
                            $year = strtr($parsedDate->format('Y'), $khmerNums);
                            echo "<span style='font-weight:bold;'>$day</span> ខែ<span style='font-weight:bold;'>$month</span> ឆ្នាំ<span style='font-weight:bold;'>$year</span>";
                        } catch (\Exception $e) {
                            echo "......... ខែ ............ ឆ្នាំ ..............";
                        }
                    } else {
                        echo "......... ខែ ............ ឆ្នាំ ..............";
                    }
                @endphp
                ខ្ញុំបាទ/នាងខ្ញុំឯកភាពសងប្រាក់ទៅតាមចំនួនដែលបានកំណត់តាមតារាងកាលវិភាគសងប្រាក់នេះរហូតគ្រប់ចំនួន ដែលការសងនេះអាចធ្វើឡើងនៅតាមរយៈភ្នាក់ងារឥណទានដោយមានវិក័យបត្រទទួលប្រាក់ ឬលោកអ្នកអាចទូទាត់ការបង់ប្រាក់តាម KHQR ឬភ្នាក់ងារវីង ហើយរាល់ការចំណាយប្រតិបត្តិការជាបន្ទុករបស់អតិថិជន។ ករណីអតិថិជនបានបង់យឺត ឬស្នើសុំបង់លើផែនការ គឺតម្រូវឲ្យទំនាក់ទំនង និងពិភាក្សាជាមួយភ្នាក់ងារឥណទានជាមុនសិន។</p>

                <p style="font-size: 11px; line-height: 2; text-align: justify; margin: 8px 0 0 0;"><span style="font-weight: bold;">ចំណាំ៖</span> ក្នុងករណីមានប្រធានស័ក្កណាមួយកើតឡើង អតិថិជនអាចទំនាក់ទំនង និងពិភាក្សាជាមួយភ្នាក់ងារឥណទាន ដើម្បីស្វែងរកដំណោះស្រាយសមរម្យណាមួយ។</p>
            </div>
        </div>
        <!-- END PRINTABLE A4 CONTENT -->

    </div>

</body>
<script>
    function generatePDF() {
        window.print();
    }
</script>
</html>