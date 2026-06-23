<?php

namespace App\Exports\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Setting;

class LoanCollectionExcelExport
{
    private const KEYS = [
        'date',
        'loan_code',
        'name',
        'phone',
        'co_name',
        'village',
        'commune',
        'currency',
        'principal',
        'interest',
        'penalty',
        'fee',
        'total',
    ];

    private const HEADERS = [
        'Date',
        'Loan No',
        'Name',
        'Phone',
        'C.O',
        'Village',
        'Commune',
        'Currency',
        'Principal',
        'Interest',
        'Penalty',
        'Fee',
        'Total',
    ];

    private const SUMMABLE_KEYS = [
        'principal',
        'interest',
        'penalty',
        'fee',
        'total',
    ];

    public function download(array $data, Request $request, ?string $fromDateStr, ?string $toDateStr, ?string $currencyFilter)
    {
        $spreadsheet = new Spreadsheet();
        
        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont)->setSize(8);
        
        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? "ប្រាក់ រហ័ស ហ្វាយនែន ម.ក";
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? "Quick Fund Finance Plc.";

        $fDate = $fromDateStr ? Carbon::parse($fromDateStr)->format('d/m/Y') : "";
        $tDate = $toDateStr ? Carbon::parse($toDateStr)->format('d/m/Y') : "";

        // Group data by currency
        $groupedData = [];
        if (strtolower($currencyFilter ?? 'all') === 'all') {
            $groupedData['Loan Collection Report'] = $data;
        } else {
            $sheetName = strtoupper($currencyFilter ?? 'ALL');
            $groupedData[$sheetName] = $data;
        }

        if (empty($groupedData)) {
            $groupedData['ALL'] = [];
        }

        $sheetIndex = 0;
        foreach ($groupedData as $sheetName => $sheetData) {
            if ($sheetIndex === 0) {
                $sheet = $spreadsheet->getActiveSheet();
            } else {
                $sheet = $spreadsheet->createSheet();
            }
            
            // Clean sheet name
            $sheetName = substr(preg_replace('/[:\/\?\*\[\]]/', '_', trim($sheetName)), 0, 31);
            $sheet->setTitle($sheetName ?: 'LoanCollection');
            $sheet->setShowGridlines(false);

            // Add Logo
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

            // Title area
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
            $sheet->setCellValue('A3', 'Loan Collection Report');
            $sheet->getStyle('A3')->getFont()->setSize(11);
            $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $filterInfo = "Period: $fDate - $tDate | Currency: $sheetName";
            $sheet->mergeCells("A4:{$titleHighestCol}4");
            $sheet->setCellValue('A4', $filterInfo);
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A4')->getFont()->setSize(10);

            $headerStyle = [
                'font' => ['bold' => true],
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

            $row = 6;
            
            // Headers
            $sheet->getRowDimension($row)->setRowHeight(50);
            $colIndex = 1;
            foreach (self::HEADERS as $header) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue("{$colLetter}{$row}", $header);
                $sheet->getStyle("{$colLetter}{$row}")->applyFromArray($headerStyle);
                $colIndex++;
            }
            $row++;

            // Data Rows
            $sumsByCurrency = [];
            if (strtolower($currencyFilter ?? 'all') === 'all') {
                $sumsByCurrency['USD'] = ['count' => 0, 'principal' => 0, 'interest' => 0, 'penalty' => 0, 'fee' => 0, 'total' => 0];
                $sumsByCurrency['KHR'] = ['count' => 0, 'principal' => 0, 'interest' => 0, 'penalty' => 0, 'fee' => 0, 'total' => 0];
            }
            
            if (empty($sheetData)) {
                $dataHighestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::KEYS));
                $sheet->mergeCells("A{$row}:{$dataHighestCol}{$row}");
                $sheet->setCellValue("A{$row}", "No records found.");
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($row)->setRowHeight(21);
                $row += 2;
            } else {
                foreach ($sheetData as $item) {
                    $sheet->getRowDimension($row)->setRowHeight(21);
                    $colIndex = 1;
                    
                    $curr = $item['currency'] ?? 'ALL';
                    if (!isset($sumsByCurrency[$curr])) {
                        $sumsByCurrency[$curr] = [
                            'count' => 0,
                            'principal' => 0,
                            'interest' => 0,
                            'penalty' => 0,
                            'fee' => 0,
                            'total' => 0,
                        ];
                    }
                    $sumsByCurrency[$curr]['count']++;

                    foreach (self::KEYS as $key) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                        $value = $this->displayValue($key, $item[$key] ?? null);

                        if ($value === null || $value === '') {
                            $sheet->setCellValue($colLetter . $row, "");
                        } elseif (is_numeric($value) && $key !== 'phone') {
                            $sheet->setCellValue($colLetter . $row, (float)$value);
                            $sheet->getStyle($colLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                            $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        } else {
                            if ($key === 'phone') {
                                $sheet->setCellValueExplicit($colLetter . $row, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            } else {
                                $sheet->setCellValue($colLetter . $row, $value);
                            }
                        }

                        if (in_array($key, self::SUMMABLE_KEYS) && is_numeric($item[$key] ?? null)) {
                            $sumsByCurrency[$curr][$key] += (float) $item[$key];
                        }

                        if ($this->isCenterKey($key)) {
                            $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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
                    if ($a === 'USD') return -1;
                    if ($b === 'USD') return 1;
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
                        $lines = [];
                        foreach ($currencies as $curr) {
                            $val = $sumsByCurrency[$curr]['count'];
                            if (count($currencies) > 1) {
                                $lines[] = $val > 0 ? $val : "";
                            } else {
                                $lines[] = "{$val} loans";
                            }
                        }
                        $sheet->setCellValue($colLetter . $row, implode("\n", $lines));
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        if (count($currencies) > 1) {
                            $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true);
                        }
                    } elseif (in_array($key, self::SUMMABLE_KEYS)) {
                        $lines = [];
                        foreach ($currencies as $curr) {
                            $val = $sumsByCurrency[$curr][$key] ?? 0;
                            $formatted = number_format($val, 2);
                            if (count($currencies) > 1) {
                                $lines[] = $formatted;
                            } else {
                                $lines[] = str_replace(',', '', $val);
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
                    }

                    $colIndex++;
                }

                $dataHighestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::KEYS));
                $sheet->getStyle("A{$row}:{$dataHighestCol}{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'name' => $excelFont, 'size' => 8],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E0E0']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);
                $sheet->getStyle("A{$row}:{$dataHighestCol}{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $row += 2;
            }

            // Column Widths
            for ($i = 1; $i <= count(self::HEADERS); $i++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $width = 14;
                if (in_array($i, [3, 5, 6, 7])) $width = 20;
                if ($i == 4) $width = 16;
                $sheet->getColumnDimension($colLetter)->setWidth($width);
            }

            $sheetIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Loan_Collection_Report_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function displayValue(string $key, mixed $value)
    {
        if ($value === null) return '';
        if (preg_match('/date$/i', $key) && !empty($value) && $value !== '-') {
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (\Exception $e) {}
        }
        
        if ($key === 'phone') {
            $cleanPhone = str_replace([' ', '-'], '', $value);
            if (strlen($cleanPhone) >= 9) {
                return substr($cleanPhone, 0, 3) . ' ' . substr($cleanPhone, 3, 3) . ' ' . substr($cleanPhone, 6);
            }
        }
        
        return $value;
    }

    private function isCenterKey(string $key)
    {
        return in_array($key, ['date', 'currency']);
    }
}
