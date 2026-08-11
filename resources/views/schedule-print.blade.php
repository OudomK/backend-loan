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
            margin: 0mm !important; /* Set to 0mm to hide browser headers/footers (URL, date) */
        }

        body {
            font-family: 'Krasar', 'Battambang', 'Khmer OS Battambang', sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 11px;
            background-color: #ececec;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 2mm 15mm 15mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .page {
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 2mm 15mm 15mm !important;
                box-shadow: none !important;
                border: none !important;
            }

            .hide-on-print {
                display: none !important;
            }

            .schedule-table th {
                background-color: #d7ffff !important;
                box-shadow: inset 0 0 0 1000px #d7ffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .schedule-table td {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        .header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 2px;
        }

        .logo-wrap {
            width: 122px;
        }

        .header img {
            width: 88px;
            height: 88px;
            object-fit: contain;
            display: block;
            border-radius: 10px;
        }

        .title-wrap {
            flex: 1;
            text-align: center;
            padding-top: 12px;
        }

        .title {
            margin: 0;
            font-family: 'Krasar', 'Battambang', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: black;
        }

        .subtitle {
            margin-top: 4px;
            font-family: 'Krasar', 'Battambang', sans-serif;
            font-size: 11px;
            font-weight: 400;
            color: black;
        }

        .line {
            border-bottom: 2px solid #111;
            margin: 8px 0 16px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0;
        }

        .info-table td {
            border: none;
            padding-bottom: 10px;
            vertical-align: middle;
        }

        .info-cell {
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .info-label {
            width: 110px;
            font-size: 11px;
            line-height: 1.22;
            display: inline-block;
        }

        .info-value {
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            line-height: 1.22;
        }

        .info-cell-address {
            align-items: flex-start;
        }

        .info-value-wrap {
            font-size: 11px;
            font-weight: 700;
            white-space: pre-line;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.22;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
        }

        .schedule-table th,
        .schedule-table td {
            border: 1px solid #111;
            padding: 5px 4px;
            text-align: center;
            font-size: 11px;
            line-height: 1.2;
        }

        .schedule-table th {
            background: #d7ffff !important;
            box-shadow: inset 0 0 0 1000px #d7ffff !important;
            color: #000 !important;
            height: 56px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            font-weight: 700;
            padding: 5px 4px;
        }

        .schedule-table .date-split-cell {
            padding-left: 10px;
            padding-right: 4px;
            text-align: left;
        }

        .date-split {
            display: inline-grid;
            grid-template-columns: auto auto;
            column-gap: 0;
            align-items: center;
            justify-content: flex-start;
            width: auto;
            height: 100%;
            white-space: nowrap;
        }

        .date-split-number {
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            text-align: left;
            font-variant-numeric: tabular-nums;
            width: 82px;
        }

        .date-split-day {
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            text-align: left;
            margin-left: 8px;
        }

        .schedule-table td {
            white-space: nowrap;
        }

        .footer {
            margin-top: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .sig {
            width: 170px;
            text-align: center;
        }

        .sig .name {
            margin-bottom: 100px;
            font-size: 13px;
            font-weight: normal;
        }

        .sig .line {
            border-bottom: 1px solid #000;
            margin: 0;
        }

        .qr {
            width: 104px;
            height: 104px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 2px;
        }

        .qr img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
    </style>
</head>

<body>

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
        style="width: 794px; margin: 0 auto; padding: 2mm 15mm 15mm; box-sizing: border-box;">

        <!-- PRINTABLE A4 CONTENT -->
        <div class="w-full text-black"
            style="font-family: 'Krasar', 'Battambang', 'Khmer OS Battambang', sans-serif; font-size: 11px;">

            <!-- Header -->
            <div class="header">
                <div class="logo-wrap">
                    <img src="{{ asset('images/logo.jpg') }}" alt="LOGO"
                        data-fallback="{{ asset('images/light_logo.png') }}"
                        onerror="this.onerror=null; this.src=this.dataset.fallback;">
                </div>
                <div class="title-wrap">
                    <h1 class="title">តារាងកាលវិភាគបង់ប្រាក់</h1>
                    <div class="subtitle">{{ $customer_info['product_name'] ?? 'Personal Loan' }}</div>
                </div>
                <div style="width: 122px;"></div>
            </div>

            <div class="line"></div>

            <!-- Customer Info Grid -->
            @if($customer_info)
                @php
                    $khmerNums = ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩'];
                    $khmerMonths = ['Jan' => 'មករា', 'Feb' => 'កុម្ភៈ', 'Mar' => 'មិនា', 'Apr' => 'មេសា', 'May' => 'ឧសភា', 'Jun' => 'មិថុនា', 'Jul' => 'កក្កដា', 'Aug' => 'សីហា', 'Sep' => 'កញ្ញា', 'Oct' => 'តុលា', 'Nov' => 'វិច្ឆិកា', 'Dec' => 'ធ្នូ'];
                @endphp
                <table class="info-table">
                    <tbody>
                        <tr>
                            <!-- Row 1 -->
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">លេខកិច្ចសន្យា:</span>
                                    <span class="info-value">{{ $customer_info['customer_id'] ?: 'QF-001' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">កាលបរិច្ឆេទខ្ចី:</span>
                                    <span class="info-value">
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
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">រយៈពេល:</span>
                                    <span class="info-value">
                                        {{ strtr(str_replace(['Months', 'Days', 'Years'], [' ខែ', ' ថ្ងៃ', ' ឆ្នាំ'], $customer_info['duration']), $khmerNums) }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <!-- Row 2 -->
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">ឈ្មោះអតិថិជន:</span>
                                    <span class="info-value">{{ $customer_info['customer_name'] ?: '-' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">ចំនួនទឹកប្រាក់:</span>
                                    <span
                                        class="info-value">{{ str_replace('.00', '', number_format((float) $customer_info['amount'], 2)) }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">រូបិយប័ណ្ណ:</span>
                                    <span class="info-value">
                                        {{ $customer_info['currency'] === 'KHR' ? 'ប្រាក់រៀល' : ($customer_info['currency'] === 'USD' ? 'ប្រាក់ដុល្លារ' : $customer_info['currency']) }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <!-- Row 3 -->
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">ភេទ:</span>
                                    <span
                                        class="info-value">{{ $customer_info['gender'] === 'Male' ? 'ប្រុស' : ($customer_info['gender'] === 'Female' ? 'ស្រី' : '-') }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">លេខអត្តសញ្ញាណ:</span>
                                    <span
                                        class="info-value">{{ strtr($customer_info['id_number'] ?: '-', $khmerNums) }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">វគ្គទី:</span>
                                    <span class="info-value">{{ strtr($customer_info['cycle'] ?: '-', $khmerNums) }}</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <!-- Row 4 -->
                            <td colspan="2">
                                <div class="info-cell info-cell-address">
                                    <span class="info-label">អាសយដ្ឋាន:</span>
                                    <span
                                        class="info-value info-value-address">{{ $customer_info['address'] ?: '-' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="info-cell">
                                    <span class="info-label">លេខទូរសព្ទ:</span>
                                    <span class="info-value">{{ $customer_info['phone_number'] ? implode(' ', str_split(preg_replace('/[^0-9]/', '', $customer_info['phone_number']), 3)) : '-' }}</span>
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

                $formatOutstandingBalance = function ($amount) use ($formatMoney) {
                    if ($amount === null || $amount === '')
                        return '-';

                    return '-' . $formatMoney(abs((float) $amount));
                };
            @endphp

            @if($isSplitMethod)
                <table class="schedule-table">
                    <thead class="bg-[#d7ffff] text-black"
                        style="-webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background-color: #d7ffff !important; box-shadow: inset 0 0 0 1000px #d7ffff !important;">
                        {{-- Hidden row to define column widths for table-layout:fixed --}}
                        <tr style="visibility: collapse; height: 0; line-height: 0; padding: 0; border: none;">
                            <th style="width: 5%;"></th>
                            <th style="width: 24%;"></th>
                            <th style="width: 14%;"></th>
                            <th style="width: 13%;"></th>
                            <th style="width: 13%;"></th>
                            <th style="width: 17%;"></th>
                            <th style="width: 14%;"></th>
                        </tr>
                        <tr>
                            <th rowspan="2" scope="col" style="width: 5%; height: 28px;">ល.រ</th>
                            <th rowspan="2" scope="col" style="width: 24%; height: 28px;">ថ្ងៃសងប្រាក់</th>
                            <th rowspan="2" scope="col" style="width: 14%; height: 28px;">ប្រាក់ដើម</th>
                            <th colspan="2" scope="col" style="width: 26%; height: 28px;">សរុបទឹកប្រាក់បង់</th>
                            <th rowspan="2" scope="col" style="width: 17%; height: 28px;">សមតុល្យប្រាក់ដើម</th>
                            <th rowspan="2" scope="col" style="width: 14%; height: 28px;">ផ្សេងៗ</th>
                        </tr>
                        <tr>
                            <th scope="col" style="width: 13%; height: 28px;">បង់ថ្ងៃទី <span class="text-red-600"
                                    style="color: red; text-decoration: underline;">{{ $day1Kh }}</span></th>
                            <th scope="col" style="width: 13%; height: 28px;">បង់ថ្ងៃទី <span class="text-red-600"
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
                                    <td>
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="date-split-cell">
                                        <span class="date-split">
                                            <span class="date-split-number">{{ $formattedDate }}</span>
                                            <span class="date-split-day">{{ $dayOfWeekKh }}</span>
                                        </span>
                                    </td>
                                    <td>
                                        {{ $formatMoney($monthPrincipal) }}
                                    </td>
                                    <td>
                                        {{ $formatMoney($payment1 ? $payment1['payment'] : 0) }}
                                    </td>
                                    <td>
                                        {{ $formatMoney($payment2 ? $payment2['payment'] : 0) }}
                                    </td>
                                    <td>
                                        {{ $formatOutstandingBalance($balance) }}
                                    </td>
                                    <td>

                                    </td>
                                </tr>
                                @php $index++; @endphp
                            @endforeach
                        @endif

                        <!-- Total Row -->
                        <tr>
                            <td style="border: none;"></td>
                            <td style="border: none; text-align: right; padding-right: 8px;">សរុប:</td>
                            <td style="background: #fff;">
                                {{ $formatMoney(collect($schedule)->sum('principal')) }}
                            </td>
                            <td style="border: none;"></td>
                            <td style="border: none;"></td>
                            <td style="border: none;"></td>
                            <td style="border: none;"></td>
                        </tr>
                    </tbody>
                </table>
            @else
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 5%;">ល.រ</th>
                            <th scope="col" style="width: 29%;">ថ្ងៃសងប្រាក់</th>
                            <th scope="col" style="width: 18%;">ប្រាក់ដើម</th>
                            <th scope="col" style="width: 19%;">សរុបទឹកប្រាក់បង់</th>
                            <th scope="col" style="width: 17%;">សមតុល្យប្រាក់ដើម</th>
                            <th scope="col" style="width: 12%;">ផ្សេងៗ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedule as $item)
                            <tr>
                                <td>
                                    {{ $item['installment_no'] ?? $loop->iteration }}
                                </td>
                                <td class="date-split-cell">
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
                                    <span class="date-split">
                                        <span class="date-split-number">{{ $formattedDate }}</span>
                                        <span class="date-split-day">{{ $dayOfWeekKh }}</span>
                                    </span>
                                </td>
                                <td>
                                    {{ $formatMoney($item['principal']) }}
                                </td>
                                <td>
                                    {{ $formatMoney($item['payment']) }}
                                </td>
                                <td>
                                    {{ $formatOutstandingBalance($item['balance']) }}
                                </td>
                                <td>

                                </td>
                            </tr>
                        @endforeach

                        <!-- Total Row -->
                        <tr>
                            <td style="border: none;"></td>
                            <td style="border: none; text-align: right; padding-right: 8px;">សរុប:</td>
                            <td style="background: #fff;">
                                {{ $formatMoney(collect($schedule)->sum('principal')) }}
                            </td>
                            <td style="border: none;"></td>
                            <td style="border: none;"></td>
                            <td style="border: none;"></td>
                        </tr>
                    </tbody>
                </table>
            @endif

            <!-- Footer Signatures -->
            <div class="footer">
                <div class="sig">
                    <div class="name">ហត្ថលេខាគណនេយ្យករ</div>
                    <div class="line"></div>
                </div>

                @php
                    $qrImage = !empty($customer_info['qr_image_url']) ? $customer_info['qr_image_url'] : null;
                @endphp
                <div class="qr">
                    @if($qrImage)
                        <img src="{{ asset($qrImage) }}" alt="QR Code">
                    @else
                        <div
                            style="border: 1px dashed #999; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #999;">
                            No QR</div>
                    @endif
                </div>

                <div class="sig">
                    <div class="name">ស្នាមមេដៃកូនបំណុល</div>
                    <div class="line"></div>
                </div>
            </div>

            <!-- Terms and Conditions Text -->
            <div style="margin-top: 28px; font-family: 'Krasar', 'Battambang', 'Khmer OS Battambang', sans-serif;">
                <p style="font-size: 11px; line-height: 2; text-align: justify; margin: 0; text-indent: 40px;">
                    បន្ទាប់&#8203;ពី&#8203;បាន&#8203;អាន
                    និង&#8203;ស្តាប់&#8203;នូវ&#8203;រាល់&#8203;ខ្លឹមសារ&#8203;នៃ&#8203;កិច្ចសន្យា&#8203;ខ្ចីប្រាក់&#8203;ថ្ងៃទី@php
                        $loanDate = $customer_info['loan_date'] ?? null;
                        if ($loanDate) {
                            try {
                                $parsedDate = \Carbon\Carbon::parse(str_replace('/', '-', $loanDate));
                                $day = strtr($parsedDate->format('d'), $khmerNums);
                                $month = $khmerMonths[$parsedDate->format('M')] ?? strtr($parsedDate->format('m'), $khmerNums);
                                $year = strtr($parsedDate->format('Y'), $khmerNums);
                                echo "$day ខែ$month ឆ្នាំ$year ";
                            } catch (\Exception $e) {
                                echo "......... ខែ ............ ឆ្នាំ ..............";
                            }
                        } else {
                            echo "......... ខែ ............ ឆ្នាំ ..............";
                        }
                    @endphp
                    ខ្ញុំបាទ/នាងខ្ញុំ&#8203;ឯកភាព&#8203;សងប្រាក់&#8203;ទៅតាម&#8203;ចំនួន&#8203;ដែល&#8203;បាន&#8203;កំណត់&#8203;តាម&#8203;តារាង&#8203;កាលវិភាគ&#8203;សងប្រាក់&#8203;នេះ&#8203;រហូត&#8203;គ្រប់&#8203;ចំនួន
                    ដែល&#8203;ការសង&#8203;នេះ&#8203;អាច&#8203;ធ្វើឡើង&#8203;នៅតាមរយៈ&#8203;ភ្នាក់ងារ&#8203;ឥណទាន&#8203;ដោយមាន&#8203;វិក័យបត្រ&#8203;ទទួលប្រាក់
                    ឬ&#8203;លោកអ្នក&#8203;អាច&#8203;ទូទាត់&#8203;ការបង់ប្រាក់&#8203;តាម KHQR ឬ&#8203;ភ្នាក់ងារ&#8203;វីង
                    ហើយ&#8203;រាល់&#8203;ការចំណាយ&#8203;ប្រតិបត្តិការ&#8203;ជាបន្ទុក&#8203;របស់&#8203;អតិថិជន។
                    ករណី&#8203;អតិថិជន&#8203;បាន&#8203;បង់យឺត ឬ&#8203;ស្នើសុំ&#8203;បង់&#8203;លើផែនការ
                    គឺ&#8203;តម្រូវឲ្យ&#8203;ទំនាក់ទំនង
                    និង&#8203;ពិភាក្សា&#8203;ជាមួយ&#8203;ភ្នាក់ងារ&#8203;ឥណទាន&#8203;ជាមុនសិន។</p>

                <p style="font-size: 11px; line-height: 2; text-align: justify; margin: 8px 0 0 0;"><span>ចំណាំ៖</span>
                    ក្នុងករណី&#8203;មាន&#8203;ប្រធានស័ក្ក&#8203;ណាមួយ&#8203;កើតឡើង អតិថិជន&#8203;អាច&#8203;ទំនាក់ទំនង
                    និង&#8203;ពិភាក្សា&#8203;ជាមួយ&#8203;ភ្នាក់ងារ&#8203;ឥណទាន
                    ដើម្បី&#8203;ស្វែងរក&#8203;ដំណោះស្រាយ&#8203;សមរម្យ&#8203;ណាមួយ។</p>
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
