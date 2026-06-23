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

class WriteOffCollectionExcelExport
{
    private const CATEGORIES = [
        'Standard Loan',
        'Special Mention Loan',
        'Substandard Loan',
        'Doubtful Loan',
        'Loss Loan',
    ];

    private const KEYS = [
        'disb_date',
        'loan_code',
        'borrower_name',
        'phone_number',
        'co_borrower',
        'guarantor',
        'village',
        'commune',
        'district',
        'province',
        'collateral_type',
        'co_repay',
        'maturity_date',
        'currency',
        'term',
        'amount',
        'amount_default',
        'default_balance',
        'recovery_amount',
        'aging',
    ];

    private const HEADERS = [
        'Disb. Date',
        'Loan No.',
        'Borrower',
        'Phone Number',
        'Co-borrower',
        'Guarantor',
        'Village',
        'Commune',
        'District',
        'Province',
        'Collateral Type',
        'C.O Repay',
        'Maturity Date',
        'Currency',
        'Term',
        'Disb. Amount',
        'Amount Default',
        'Default Balance',
        'Recovery Amount',
        'Aging',
    ];

    public function download(array $data, Request $request, ?string $fromDateStr, ?string $toDateStr, ?string $currencyFilter)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont)->setSize(8);

        $groupedData = [];
        if (strtolower($currencyFilter ?? 'all') === 'all') {
            $groupedData['ALL'] = $data;
        } else {
            $sheetName = strtoupper($currencyFilter ?? 'ALL');
            $groupedData[$sheetName] = $data;
        }

        if (empty($groupedData) || empty($groupedData[array_key_first($groupedData)])) {
            $groupedData['ALL'] = [];
        }

        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? "ប្រាក់ រហ័ស ហ្វាយនែន ម.ក";
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? "Quick Fund Finance Plc.";
        $reportTitle = "Write-Off Collection Report";

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
                $sheetName = 'WriteOffCollection';
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

            $titleHighestCol = "N";

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

            $row = 6;

            foreach (self::CATEGORIES as $category) {
                // Filter data for this category
                $categoryData = $sheetData[$category] ?? [];

                // Category Title
                $dataHighestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::KEYS));
                $sheet->mergeCells("A{$row}:{$dataHighestCol}{$row}");
                $sheet->setCellValue("A{$row}", strtoupper($category) . " (" . count($categoryData) . " records)");
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F57C00']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(25);
                $row++;

                // Headers
                $sheet->getRowDimension($row)->setRowHeight(50);
                $colIndex = 1;
                foreach (self::HEADERS as $header) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue("{$colLetter}{$row}", $header);
                    $sheet->getStyle("{$colLetter}{$row}")->applyFromArray($headerStyle);

                    $width = 14;
                    if (in_array($colIndex, [3, 5, 6])) $width = 20; // Names
                    if (in_array($colIndex, [7, 8, 9, 10])) $width = 16; // Locations
                    if (in_array($colIndex, [16, 17, 18, 19])) $width = 18; // Amounts

                    $sheet->getColumnDimension($colLetter)->setWidth($width);
                    $colIndex++;
                }
                $row++;

                if (empty($categoryData)) {
                    $sheet->mergeCells("A{$row}:{$titleHighestCol}{$row}");
                    $sheet->setCellValue("A{$row}", "No records found.");
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getRowDimension($row)->setRowHeight(21);
                    $row += 2;
                    continue;
                }

                $sumsByCurrency = [];
                if (strtolower($currencyFilter ?? 'all') === 'all') {
                    $sumsByCurrency['USD'] = ['count' => 0, 'amount' => 0, 'amount_default' => 0, 'default_balance' => 0, 'recovery_amount' => 0];
                    $sumsByCurrency['KHR'] = ['count' => 0, 'amount' => 0, 'amount_default' => 0, 'default_balance' => 0, 'recovery_amount' => 0];
                }

                foreach ($categoryData as $item) {
                    $sheet->getRowDimension($row)->setRowHeight(21);
                    $colIndex = 1;
                    foreach (self::KEYS as $key) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                        $value = $this->displayValue($key, $item[$key] ?? null);

                        if ($value === null || $value === '') {
                            $sheet->setCellValue($colLetter . $row, "");
                        } elseif (is_numeric($value) && $key !== 'phone_number') {
                            $sheet->setCellValue($colLetter . $row, (float)$value);
                            if ($this->isIntegerKey($key)) {
                                $sheet->getStyle($colLetter . $row)->getNumberFormat()->setFormatCode('#,##0');
                            } else {
                                $sheet->getStyle($colLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                            }
                            $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        } else {
                            $sheet->setCellValue($colLetter . $row, $value);
                        }

                        $curr = $item['currency'] ?? 'ALL';
                        if (!isset($sumsByCurrency[$curr])) {
                            $sumsByCurrency[$curr] = [
                                'count' => 0,
                                'amount' => 0,
                                'amount_default' => 0,
                                'default_balance' => 0,
                                'recovery_amount' => 0,
                            ];
                        }
                        $sumsByCurrency[$curr]['count']++;

                        if (isset($sumsByCurrency[$curr][$key]) && $key !== 'count' && is_numeric($item[$key] ?? null)) {
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
                    } elseif (isset($sumsByCurrency[$currencies[0] ?? 'ALL'][$key]) && $key !== 'count') {
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
                
                $row += 2; // Spacing between categories
            }
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Write_Off_Collection_Report_' . date('Ymd_His') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    private function displayValue(string $key, mixed $value)
    {
        if ($value === null) return '';
        if (in_array($key, ['disb_date', 'maturity_date']) && !empty($value) && $value !== '-') {
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (\Exception $e) {}
        }
        return $value;
    }

    private function isIntegerKey(string $key): bool
    {
        return $key === 'term' || $key === 'aging';
    }

    private function isCenterKey(string $key): bool
    {
        return in_array($key, [
            'disb_date',
            'currency',
            'term',
            'maturity_date',
            'aging',
        ]);
    }
}
