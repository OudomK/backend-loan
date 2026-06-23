<?php

namespace App\Exports\Excel;

use App\Models\Setting;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class WriteOffExcelExport
{
    private const KEYS = [
        'written_off_date',
        'disbursement_date',
        'loan_code',
        'product_name',
        'customer_name',
        'village',
        'commune',
        'district',
        'province',
        'amount',
        'currency',
        'rate',
        'monthly_interest_rate',
        'term',
        'tenor',
        'payment_method',
        'loan_cycle',
        'refinance_fee',
        'admin_fee',
        'restructure_fee',
        'collateral_type',
        'co_disburse',
        'co_repay',
        'amount_write_off',
        'write_off_balance',
        'principal_collected',
        'interest_collected',
        'recovery_amount',
        'maturity_date',
        'write_off_reason',
        'status',
        'classify_wo',
    ];

    private const HEADERS = [
        'Written-Off Date',
        'Disbursement Date',
        'Loan Contract',
        'Product',
        'Client Name',
        'Village',
        'Commune',
        'District',
        'Province',
        'Disburse Amount',
        'Currency',
        'Rate',
        'Monthly Rate',
        'Term',
        'Tenor',
        'Payment Method',
        'Loan Cycle',
        'Refinance Fee',
        'Loan Disb. Fee',
        'Restructure Fee',
        'Collateral Type',
        'C.O Disburse',
        'C.O Repay',
        'Amount Write-Off',
        'W/O Balance',
        'Principal Collected',
        'Interest Collected',
        'Recovery Amount',
        'Maturity Date',
        'W/O Reason',
        'Status',
        'Classify WO',
    ];

    public function download(array $data, Request $request, ?string $fromDateStr, ?string $toDateStr, ?string $currencyFilter)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont)->setSize(8);

        $groupedData = [];
        if (strtolower($currencyFilter ?? 'all') === 'all') {
            $groupedData['WRITE-OFF REPORT'] = $data;
        } else {
            $sheetName = strtoupper($currencyFilter ?? 'ALL');
            $groupedData[$sheetName] = $data;
        }

        if (empty($groupedData) || empty($groupedData[array_key_first($groupedData)])) {
            $groupedData['WRITE-OFF REPORT'] = [];
        }

        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? "ប្រាក់ រហ័ស ហ្វាយនែន ម.ក";
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? "Quick Fund Finance Plc.";
        $reportTitle = "Write-Off Report";

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
                $sheetName = 'WriteOff';
            }

            $sheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($sheet);
            $sheet->setShowGridlines(false);

            $filterInfo = "Period: $fDate - $tDate | Currency: $sheetName";

            // 1. Title area & Logo
            $drawing = new Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Logo');
            $logoPath = public_path('images/logo.jpg');
            if (file_exists($logoPath)) {
                $drawing->setPath($logoPath);
                $drawing->setHeight(90);
                $drawing->setCoordinates('A1');
                $drawing->setOffsetY(5);
                $drawing->setWorksheet($sheet);
            }

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

                $width = 14;
                if ($colIndex == 4 || $colIndex == 5)
                    $width = 24; // Loan Contract, Product
                if (in_array($colIndex, [6, 7, 8, 9]))
                    $width = 18; // Locations
                if (in_array($colIndex, [16, 21, 30, 32]))
                    $width = 18; // Pay Method, Collateral, Reason, Classify
                if ($colIndex == 22 || $colIndex == 23)
                    $width = 16; // COs
                // Amount columns: 10, 18, 19, 20, 24, 25, 26, 27, 28
                if (in_array($colIndex, [10, 18, 19, 20, 24, 25, 26, 27, 28]))
                    $width = 20;

                $sheet->getColumnDimension($colLetter)->setWidth($width);

                $colIndex++;
            }

            $row = 7;
            $sumsByCurrency = [];
            if (strtolower($currencyFilter ?? 'all') === 'all') {
                $sumsByCurrency['USD'] = ['count' => 0, 'amount' => 0, 'refinance_fee' => 0, 'admin_fee' => 0, 'restructure_fee' => 0, 'amount_write_off' => 0, 'write_off_balance' => 0, 'principal_collected' => 0, 'interest_collected' => 0, 'recovery_amount' => 0];
                $sumsByCurrency['KHR'] = ['count' => 0, 'amount' => 0, 'refinance_fee' => 0, 'admin_fee' => 0, 'restructure_fee' => 0, 'amount_write_off' => 0, 'write_off_balance' => 0, 'principal_collected' => 0, 'interest_collected' => 0, 'recovery_amount' => 0];
            }

            foreach ($sheetData as $item) {
                $curr = strtoupper($item['currency'] ?? 'ALL');
                if (!isset($sumsByCurrency[$curr])) {
                    $sumsByCurrency[$curr] = [
                        'count' => 0,
                        'amount' => 0,
                        'refinance_fee' => 0,
                        'admin_fee' => 0,
                        'restructure_fee' => 0,
                        'amount_write_off' => 0,
                        'write_off_balance' => 0,
                        'principal_collected' => 0,
                        'interest_collected' => 0,
                        'recovery_amount' => 0,
                    ];
                }
                $sumsByCurrency[$curr]['count']++;

                $sheet->getRowDimension($row)->setRowHeight(21);
                $colIndex = 1;
                foreach (self::KEYS as $key) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $value = $this->displayValue($key, $item[$key] ?? null);

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
                $dataHighestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::KEYS));
                $sheet->getStyle("A{$row}:{$dataHighestCol}{$row}")->applyFromArray($dataStyle);
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
                                $lines[] = $formatted;
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
                        $sheet->setCellValue($colLetter . $row, $currencies[0] ?? '');
                    }
                    $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $sheet->setCellValue($colLetter . $row, '');
                }

                $colIndex++;
            }

            $dataHighestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::KEYS));
            $sheet->getStyle("A{$row}:{$dataHighestCol}{$row}")->applyFromArray([
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
        $fileName = 'Write_Off_Report_' . date('Ymd_His') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    private function displayValue(string $key, mixed $value)
    {
        if ($value === null) return '';
        if (in_array($key, ['written_off_date', 'disbursement_date', 'maturity_date']) && !empty($value) && $value !== '-') {
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (\Exception $e) {}
        }
        if ($key === 'rate' || $key === 'monthly_interest_rate') {
            $rate = is_numeric($value) ? (float) $value : (float) str_replace('%', '', (string) $value);
            return number_format($rate, 2) . '%';
        }
        if ($key === 'payment_method') {
            return \App\Support\FormatHelper::formatPaymentMethod((string) $value);
        }
        return $value;
    }

    private function isIntegerKey(string $key): bool
    {
        return $key === 'term' || $key === 'loan_cycle';
    }

    private function isCenterKey(string $key): bool
    {
        return in_array($key, [
            'written_off_date',
            'disbursement_date',
            'currency',
            'rate',
            'monthly_interest_rate',
            'term',
            'tenor',
            'loan_cycle',
            'maturity_date',
            'status'
        ]);
    }
}
