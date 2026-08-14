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

class QualityPortfolioExcelExport
{
    private const KEYS = [
        'co_code', 'co_name', 'product_name', 'no_disb_old', 'no_disb_new', 'no_disb_total',
        'disb_amount_old', 'disb_amount_new', 'disb_amount_total', 'disb_amount_extra',
        'total_client', 'loan_os', 'interest_os', 'fee_os', 'no_of_client', 'principal_collected',
        'interest_collected', 'fee_collected', 'penalty_collected', 'paid_off_collected',
        'recovery', 'principal_due', 'interest_due', 'fee_due', 'total_arrears', 'repayment_rate',
        'no_par_1', 'amount_par_1', 'percent_par_1', 'no_par_1_29', 'amount_par_1_29', 'percent_par_1_29',
        'no_par_30', 'amount_par_30', 'percent_par_30', 'no_wo_month', 'principal_wo_month',
        'interest_wo_month', 'fee_wo_month', 'no_wo_ytd', 'principal_wo_ytd', 'interest_wo_ytd', 'fee_wo_ytd',
    ];

    private const COL_LABELS = [
        '', '', '', 'Old', 'New', 'Total', 'Old', 'New', 'Total', '', '', '', '', '', '',
        'Principal Collected', 'Interest Collected', 'Fee Collected', 'Penalty Collected', 'Paid-off Collected',
        'Recovery', 'Principal Due', 'Interest Due', 'Fee Due', 'Total Amount in Arrears', '% Repayment Rate',
        'No. of PAR 1', 'Amount of PAR 1', '% PAR 1', 'No. of PAR 1_29', 'Amount of PAR 1_29', '% PAR 1-29',
        'No. of PAR 30', 'Amount of PAR 30', '% PAR 30', 'No. WO', 'WO Principal', 'WO Interest', 'WO Fee',
        'No. WO', 'WO Principal', 'WO Interest', 'WO Fee',
    ];

    public function download(array $data, Request $request, ?string $fromDateStr, ?string $toDateStr, ?string $currencyFilter)
    {
        $spreadsheet = new Spreadsheet();
        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont)->setSize(8);

        $processedData = [];
        if (strtolower($currencyFilter ?? 'all') === 'all') {
            $tempMap = [];
            foreach ($data as $row) {
                $comboKey = $row['co_code'] . '_' . $row['product_name'];
                if (!isset($tempMap[$comboKey])) {
                    $tempMap[$comboKey] = [
                        'co_code' => $row['co_code'],
                        'co_name' => $row['co_name'],
                        'product_name' => $row['product_name'],
                        'currencies' => [],
                    ];
                }
                $currency = strtoupper($row['currency'] ?? 'USD');
                $tempMap[$comboKey]['currencies'][$currency] = $row;
            }

            foreach ($tempMap as $comboData) {
                $processedData[] = $comboData;
            }
            $groupedData = ['QUALITY PORTFOLIO' => $processedData];
        } else {
            foreach ($data as $row) {
                $processedData[] = [
                    'co_code' => $row['co_code'],
                    'co_name' => $row['co_name'],
                    'product_name' => $row['product_name'],
                    'currencies' => [strtoupper($row['currency'] ?? 'ALL') => $row]
                ];
            }
            $sheetName = strtoupper($currencyFilter ?? 'ALL');
            $groupedData = [$sheetName => $processedData];
        }

        if (empty($processedData)) {
            $groupedData['QUALITY PORTFOLIO'] = [];
        }

        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? '';
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? '';
        $reportTitle = "Quality Portfolio Report";
        
        $fDate = $fromDateStr ? \Carbon\Carbon::parse($fromDateStr)->format('d/m/Y') : "";
        $tDate = $toDateStr ? \Carbon\Carbon::parse($toDateStr)->format('d/m/Y') : "";
        
        $filterInfo = "Period: $fDate - $tDate";
        if (!$fromDateStr) {
            $filterInfo = "As At: $tDate";
        }

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

        $sheetIndex = 0;
        foreach ($groupedData as $sheetName => $sheetData) {
            // sanitize sheet name
            $sheetName = substr(preg_replace('/[:\/\?\*\[\]]/', '_', trim($sheetName)), 0, 31);
            if (empty($sheetName)) {
                $sheetName = 'Quality Portfolio';
            }

            $sheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($sheet, $sheetIndex);
            $sheet->setShowGridlines(false);
            $sheetIndex++;

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

            $tableHighestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::KEYS));
            
            // Table Headers (Starts at row 7, 8, 9)
            $sheet->getRowDimension(7)->setRowHeight(17);
            $sheet->getRowDimension(8)->setRowHeight(17);
            $sheet->getRowDimension(9)->setRowHeight(16);

            $mergeWithStyle = function ($sC, $eC, $sR, $eR, $label) use ($sheet, $headerStyle) {
                $sColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sC + 1);
                $eColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($eC + 1);
                
                if ($sC !== $eC || $sR !== $eR) {
                    $sheet->mergeCells("{$sColLetter}{$sR}:{$eColLetter}{$eR}");
                }
                
                $sheet->getStyle("{$sColLetter}{$sR}:{$eColLetter}{$eR}")->applyFromArray($headerStyle);
                
                if ($label !== null) {
                    $sheet->setCellValue("{$sColLetter}{$sR}", $label);
                }
            };

            $mergeWithStyle(0, 0, 7, 9, "Code");
            $mergeWithStyle(1, 1, 7, 9, "CO Name");
            $mergeWithStyle(2, 2, 7, 9, "Product");

            $mergeWithStyle(3, 14, 7, 7, "PORTFOLIO SIZE (End of Period)");
            $mergeWithStyle(3, 5, 8, 8, "No. Disb.");
            $mergeWithStyle(6, 8, 8, 8, "Disb. Amount");
            $mergeWithStyle(9, 9, 8, 9, "Disb. Amount Extra");
            $mergeWithStyle(10, 10, 8, 9, "Total Client");
            $mergeWithStyle(11, 11, 8, 9, "Loan OS");
            $mergeWithStyle(12, 12, 8, 9, "Interest OS");
            $mergeWithStyle(13, 13, 8, 9, "Fee OS");
            $mergeWithStyle(14, 14, 8, 9, "No. Of Client");

            $mergeWithStyle(15, 25, 7, 7, "PORTFOLIO MUTATION (This Period)");
            $mergeWithStyle(26, 34, 7, 7, "Portfolio At Risk");
            $mergeWithStyle(35, 38, 7, 7, "Loan Write-Off (Current Month)");
            $mergeWithStyle(39, 42, 7, 7, "Loan Write-Off (Year to Date)");

            // For columns 15 to 42, they don't have a sub-header in row 8, so we merge row 8 and 9 vertically
            for ($i = 15; $i <= 42; $i++) {
                $mergeWithStyle($i, $i, 8, 9, null);
            }

            // Apply style to row 9 for columns 3 to 8 because they are not vertically merged
            for ($i = 3; $i <= 8; $i++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $sheet->getStyle("{$colLetter}9")->applyFromArray($headerStyle);
            }

            for ($i = 0; $i < count(self::COL_LABELS); $i++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                
                $targetRow = 9;
                if ($i >= 15) {
                    $targetRow = 8; // Because they are merged 8:9, we must set text in row 8
                } elseif ($i < 3) {
                    $targetRow = 7; // Merged 7:9, label already set
                }

                if (self::COL_LABELS[$i] !== '') {
                    $sheet->setCellValue("{$colLetter}{$targetRow}", self::COL_LABELS[$i]);
                }

                $width = (self::COL_LABELS[$i] === '' ? 10 : strlen(self::COL_LABELS[$i])) * 1.1 + 8;
                if ($i == 0) $width = 12;
                elseif ($i == 1) $width = 30;
                elseif ($i == 2) $width = 22;
                elseif ($width < 18) $width = 18;
                
                $sheet->getColumnDimension($colLetter)->setWidth($width);
            }

            $row = 10;
            $sums = array_fill_keys(self::KEYS, 0);

            $globalSums = []; // Keep track of sums per currency and overall

            foreach ($sheetData as $item) {
                $sheet->getRowDimension($row)->setRowHeight(35); // Taller for multi-line
                $colIndex = 1;
                foreach (self::KEYS as $key) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

                    if (in_array($key, ['co_code', 'co_name', 'product_name'])) {
                        $sheet->setCellValue($colLetter . $row, $item[$key] ?? '');
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $colIndex++;
                        continue;
                    }

                    if ($this->isIntegerKey($key)) {
                        if ($key === 'total_client' || $key === 'no_of_client') {
                            $val = $this->uniqueBorrowerCount([$item]);
                        } else {
                            $val = 0;
                            foreach ($item['currencies'] as $curData) {
                                $val += (int)($curData[$key] ?? 0);
                            }
                        }
                        $sheet->setCellValue($colLetter . $row, $val);
                        $sheet->getStyle($colLetter . $row)->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $globalSums['ALL'][$key] = ($globalSums['ALL'][$key] ?? 0) + $val;
                    } elseif ($this->isSummableKey($key)) {
                        $lines = [];
                        foreach ($item['currencies'] as $cur => $curData) {
                            $val = (float)($curData[$key] ?? 0);
                            $globalSums[$cur][$key] = ($globalSums[$cur][$key] ?? 0) + $val;
                            if ($val == 0 && count($item['currencies']) > 1) {
                                // Keep zero if showing multiple currencies for clarity, or just show it anyway.
                                $lines[] = "{$cur} 0.00";
                            } else {
                                $lines[] = "{$cur} " . number_format($val, 2);
                            }
                        }
                        if (empty($lines)) {
                            $sheet->setCellValue($colLetter . $row, "0.00");
                        } else {
                            $sheet->setCellValue($colLetter . $row, implode("\n", $lines));
                            $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true);
                        }
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } elseif ($this->isPercentKey($key)) {
                        $lines = [];
                        foreach ($item['currencies'] as $cur => $curData) {
                            $val = (float)($curData[$key] ?? 0);
                            $lines[] = "{$cur} " . number_format($val, 2) . "%";
                        }
                        if (empty($lines)) {
                            $sheet->setCellValue($colLetter . $row, "0.00%");
                        } else {
                            $sheet->setCellValue($colLetter . $row, implode("\n", $lines));
                            $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true);
                        }
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } else {
                        $sheet->setCellValue($colLetter . $row, "");
                    }

                    $colIndex++;
                }
                $sheet->getStyle("A{$row}:{$tableHighestCol}{$row}")->applyFromArray($dataStyle);
                $row++;
            }

            // Total Row
            $sheet->getRowDimension($row)->setRowHeight(35);
            $sheet->setCellValue('A' . $row, 'Total');
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $colIndex = 1;
            foreach (self::KEYS as $key) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                
                if ($colIndex <= 3) {
                    $colIndex++;
                    continue;
                }

                if ($this->isIntegerKey($key)) {
                    if ($key === 'total_client' || $key === 'no_of_client') {
                        $val = $this->uniqueBorrowerCount($sheetData);
                    } else {
                        $val = $globalSums['ALL'][$key] ?? 0;
                    }
                    $sheet->setCellValue($colLetter . $row, $val);
                    $sheet->getStyle($colLetter . $row)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                } elseif ($this->isSummableKey($key)) {
                    $lines = [];
                    // Extract all unique currencies seen
                    $allCurs = [];
                    foreach ($globalSums as $cur => $sumsMap) {
                        if ($cur !== 'ALL') $allCurs[] = $cur;
                    }
                    foreach ($allCurs as $cur) {
                        $val = $globalSums[$cur][$key] ?? 0;
                        $lines[] = "{$cur} " . number_format($val, 2);
                    }
                    if (empty($lines)) {
                        $sheet->setCellValue($colLetter . $row, "0.00");
                    } else {
                        $sheet->setCellValue($colLetter . $row, implode("\n", $lines));
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true);
                    }
                    $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                } elseif ($this->isPercentKey($key)) {
                    $lines = [];
                    $allCurs = [];
                    foreach ($globalSums as $cur => $sumsMap) {
                        if ($cur !== 'ALL') $allCurs[] = $cur;
                    }
                    foreach ($allCurs as $cur) {
                        $curSums = $globalSums[$cur] ?? [];
                        $lines[] = "{$cur} " . $this->totalPercentLabel($key, $curSums);
                    }
                    if (empty($lines)) {
                        $sheet->setCellValue($colLetter . $row, "0.00%");
                    } else {
                        $sheet->setCellValue($colLetter . $row, implode("\n", $lines));
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true);
                    }
                    $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                } else {
                    $sheet->setCellValue($colLetter . $row, "");
                }
                
                $colIndex++;
            }

            $sheet->getStyle("A{$row}:{$tableHighestCol}{$row}")->applyFromArray([
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
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Quality_Portfolio_Report_' . date('Ymd_His') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    private function isPercentKey(string $key): bool
    {
        return str_contains($key, 'percent') || str_contains($key, 'rate');
    }

    private function isIntegerKey(string $key): bool
    {
        return $key === 'co_code' || $key === 'total_client' || str_starts_with($key, 'no_');
    }

    private function isSummableKey(string $key): bool
    {
        return !$this->isPercentKey($key) && !in_array($key, ['co_code', 'co_name', 'product_name']);
    }

    private function displayValue(string $key, mixed $value): string
    {
        if (is_numeric($value) && $this->isPercentKey($key)) {
            return number_format((float)$value, 2) . "%";
        }
        return (string)$value;
    }

    private function uniqueBorrowerCount(array $data): int
    {
        $borrowerIds = [];
        $sum = 0;
        
        foreach ($data as $row) {
            $currenciesData = isset($row['currencies']) ? $row['currencies'] : ['ALL' => $row];
            
            foreach ($currenciesData as $curData) {
                $ids = $curData['borrower_ids'] ?? [];
                if (is_array($ids) && !empty($ids)) {
                    foreach ($ids as $id) {
                        if (!empty($id)) {
                            $borrowerIds[$id] = true;
                        }
                    }
                } else {
                    $sum += (int)($curData['total_client'] ?? 0);
                }
            }
        }

        if (!empty($borrowerIds)) {
            return count($borrowerIds);
        }

        return $sum;
    }

    private function totalPercentLabel(string $key, array $sums): string
    {
        $numerator = 0;
        $denominator = 0;

        switch ($key) {
            case 'repayment_rate':
                $numerator = $sums['principal_collected'] ?? 0;
                $denominator = $sums['principal_due'] ?? 0;
                break;
            case 'percent_par_1':
                $numerator = $sums['amount_par_1'] ?? 0;
                $denominator = $sums['loan_os'] ?? 0;
                break;
            case 'percent_par_1_29':
                $numerator = $sums['amount_par_1_29'] ?? 0;
                $denominator = $sums['loan_os'] ?? 0;
                break;
            case 'percent_par_30':
                $numerator = $sums['amount_par_30'] ?? 0;
                $denominator = $sums['loan_os'] ?? 0;
                break;
        }

        if ($denominator <= 0) {
            return "0.00%";
        }
        return number_format(($numerator / $denominator) * 100, 2) . "%";
    }
}
