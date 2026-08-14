<?php

namespace App\Exports\Excel;

use App\Models\Setting;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class InterestIncomeExcelExport
{
    private const KEYS = [
        'disb_date',
        'customer_code',
        'customer_name',
        'loan_amount',
        'currency',
        'interest_rate',
        'term',
        'payment_frequency',
        'repayment_method',
        'product_name',
        'interest_paid',
        'fee_paid',
        'total',
    ];

    private const HEADERS = [
        'Disb. Date',
        'Client Code',
        'Client Name',
        'Loan Amount',
        'Currency',
        'Interest Rate',
        'Term',
        'Frequency',
        'Repay. Method',
        'Product',
        'Interest Paid',
        'Fee/Admin',
        'Total',
    ];

    public function download(array $data, Request $request, ?string $fromDateStr, ?string $toDateStr, ?string $currencyFilter)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont)->setSize(8);

        $groupedData = [];
        if (strtolower($currencyFilter ?? 'all') === 'all') {
            $groupedData['INTEREST INCOME'] = $data;
        } else {
            $sheetName = strtoupper($currencyFilter ?? 'ALL');
            $groupedData[$sheetName] = $data;
        }

        if (empty($groupedData) || empty($groupedData[array_key_first($groupedData)])) {
            $groupedData['INTEREST INCOME'] = [];
        }

        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? '';
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? '';
        $reportTitle = "Interest Income & Fee Admin Report";

        $fDate = $fromDateStr ? Carbon::parse($fromDateStr)->format('d/m/Y') : "";
        $tDate = $toDateStr ? Carbon::parse($toDateStr)->format('d/m/Y') : "";

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 8],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D3D3D3']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ]
        ];

        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ];

        foreach ($groupedData as $sheetName => $sheetData) {
            $sheetName = substr(preg_replace('/[:\/\?\*\[\]]/', '_', trim($sheetName)), 0, 31);
            if (empty($sheetName)) {
                $sheetName = 'InterestIncome';
            }

            $sheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($sheet);
            $sheet->setShowGridlines(false);

            $filterInfo = "Period: $fDate - $tDate | Currency: $sheetName";

            $sheet->getRowDimension(1)->setRowHeight(45);

            $titleHighestCol = 'N';

            $sheet->mergeCells("A1:{$titleHighestCol}1");
            $sheet->setCellValue('A1', $khmerCompanyName);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells("A2:{$titleHighestCol}2");
            $sheet->setCellValue('A2', $englishCompanyName);
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells("A3:{$titleHighestCol}3");
            $sheet->setCellValue('A3', $reportTitle);
            $sheet->getStyle('A3')->getFont()->setSize(11);
            $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells("A4:{$titleHighestCol}4");
            $sheet->setCellValue('A4', $filterInfo);
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A4')->getFont()->setSize(10);

            // Table Headers
            $sheet->getRowDimension(6)->setRowHeight(50);
            $colIndex = 1;
            foreach (self::HEADERS as $header) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue("{$colLetter}6", $header);
                $sheet->getStyle("{$colLetter}6")->applyFromArray($headerStyle);

                $width = 15;
                if ($colIndex == 3)
                    $width = 24; // Client Name
                if ($colIndex == 11)
                    $width = 22; // Product
                if ($colIndex == 9)
                    $width = 18; // Repay. Method
                if (in_array($colIndex, [4, 12, 13, 14]))
                    $width = 20; // Amounts
                $sheet->getColumnDimension($colLetter)->setWidth($width);

                $colIndex++;
            }

            $row = 7;
            $sumsByCurrency = [];

            foreach ($sheetData as $item) {
                $curr = strtoupper($item['currency'] ?? 'ALL');
                if (!isset($sumsByCurrency[$curr])) {
                    $sumsByCurrency[$curr] = [
                        'count' => 0,
                        'loan_amount' => 0,
                        'interest_paid' => 0,
                        'fee_paid' => 0,
                        'total' => 0,
                    ];
                }
                $sumsByCurrency[$curr]['count']++;

                $sheet->getRowDimension($row)->setRowHeight(21);
                $colIndex = 1;
                foreach (self::KEYS as $key) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    
                    if ($key === 'customer_code') {
                        $code = $item['customer_code'] ?? 'N/A';
                        $cycle = $item['loan_cycle'] ?? '';
                        $value = $code . ($cycle ? '-C' . $cycle : '');
                    } else {
                        $value = $this->displayValue($key, $item[$key] ?? null);
                    }

                    $isNum = is_numeric($value);
                    $isCenter = $this->isCenterKey($key);

                    if ($value === null || $value === '') {
                        $sheet->setCellValue($colLetter . $row, "");
                    } elseif ($isNum) {
                        $floatVal = (float) $value;
                        $sheet->setCellValue($colLetter . $row, $floatVal);

                        if ($this->isIntegerKey($key)) {
                            $sheet->getStyle($colLetter . $row)->getNumberFormat()->setFormatCode('#,##0');
                        } else {
                            $sheet->getStyle($colLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                        }
                    } else {
                        $sheet->setCellValue($colLetter . $row, $value);
                    }

                    if (isset($sumsByCurrency[$curr][$key]) && is_numeric($item[$key] ?? null)) {
                        $sumsByCurrency[$curr][$key] += (float) $item[$key];
                    }

                    if ($isCenter) {
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    } elseif ($isNum) {
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    $colIndex++;
                }
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray($dataStyle);
                $row++;
            }

            // Total Row
            $currencies = array_keys($sumsByCurrency);
            usort($currencies, function ($a, $b) {
                if ($a === 'USD')
                    return -1;
                if ($b === 'USD')
                    return 1;
                return strcmp($a, $b);
            });

            $sheet->getRowDimension($row)->setRowHeight(count($currencies) > 1 ? 45 : 25);
            $colIndex = 1;
            foreach (self::KEYS as $key) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

                if ($colIndex == 1) {
                    $sheet->setCellValue($colLetter . $row, 'TOTAL');
                    $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } elseif ($colIndex == 2) {
                    $sheet->setCellValue($colLetter . $row, count($sheetData) . ' loans');
                    $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } elseif (isset($sumsByCurrency[$currencies[0] ?? 'ALL'][$key])) {
                    $lines = [];
                    foreach ($currencies as $curr) {
                        $val = $sumsByCurrency[$curr][$key];
                        $formatted = number_format($val, 2);
                        if (count($currencies) > 1) {
                            $lines[] = "{$curr} {$formatted}";
                        } else {
                            $lines[] = str_replace(',', '', $val); // Let excel format it
                        }
                    }
                    if (count($currencies) > 1) {
                        $sheet->setCellValue($colLetter . $row, implode("\n", $lines));
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true);
                    } else {
                        $val = $lines[0] ?? 0;
                        $sheet->setCellValue($colLetter . $row, is_numeric($val) ? (float) $val : $val);
                        $sheet->getStyle($colLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                } elseif ($key === 'currency') {
                    if (count($currencies) > 1) {
                        $sheet->setCellValue($colLetter . $row, implode("\n", $currencies));
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true);
                    } else {
                        $sheet->setCellValue($colLetter . $row, $currencies[0] ?? $sheetName);
                    }
                    $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $sheet->setCellValue($colLetter . $row, '');
                }

                $colIndex++;
            }

            $sheet->getStyle("A{$row}:{$titleHighestCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'name' => $excelFont, 'size' => 8],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                    'top' => [
                        'borderStyle' => Border::BORDER_DOUBLE,
                    ]
                ]
            ]);
            $sheet->getStyle("A{$row}:{$titleHighestCol}{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Interest_Income_Report_' . date('Ymd_His') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    private function displayValue(string $key, mixed $value)
    {
        if ($value === null) return '';
        if (in_array($key, ['disb_date', 'maturity_date', 'written_off_date']) && !empty($value) && $value !== '-') {
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (\Exception $e) {}
        }
        if ($key === 'interest_rate') {
            $rate = is_numeric($value) ? (float) $value : (float) str_replace('%', '', (string) $value);
            return number_format($rate, 2) . '%';
        }
        if ($key === 'repayment_method') {
            return \App\Support\FormatHelper::formatPaymentMethod((string) $value);
        }
        if ($key === 'payment_frequency') {
            $s = str_replace('_monthly', '', (string) $value);
            $s = str_replace('_', ' ', $s);
            $s = ucwords($s);
            return trim($s) === '' ? '-' : trim($s);
        }
        return $value;
    }

    private function isIntegerKey(string $key): bool
    {
        return $key === 'term';
    }

    private function isCenterKey(string $key): bool
    {
        return in_array($key, [
            'disb_date',
            'currency',
            'interest_rate',
            'term',
            'payment_frequency'
        ]);
    }
}
