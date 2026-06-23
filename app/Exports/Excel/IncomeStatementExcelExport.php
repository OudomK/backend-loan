<?php

namespace App\Exports\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\Setting;
use Illuminate\Http\Request;

class IncomeStatementExcelExport
{
    public function download(array $data, Request $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setShowGridlines(false);
        $sheet->setTitle('IncomeStatement');

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';

        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont);
        $spreadsheet->getDefaultStyle()->getFont()->setSize(9);

        $companyName = Setting::where('key', 'company_name')->value('value') ?? 'QUICK FUND';
        $reportTitle = 'INCOME STATEMENT';
        $reportSubtitle = 'របាយការណ៍ចំណូល និង ចំណាយ';

        $fromDate = $data['period']['from_date'] ?? '';
        $toDate = $data['period']['to_date'] ?? '';
        $periodLabel = "For the period " . \Carbon\Carbon::parse($fromDate)->format('F d, Y') . " to " . \Carbon\Carbon::parse($toDate)->format('F d, Y');

        $currencies = $data['currencies'] ?? ['USD'];
        $extraCols = count($currencies);
        $highestColIndex = 1 + $extraCols;
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($highestColIndex + 1);

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(45);
        for ($i = 0; $i <= $extraCols; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 2);
            $sheet->getColumnDimension($colLetter)->setWidth(18);
        }

        // LOGO
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $logoPath = Setting::where('key', 'company_logo')->value('value');

        $validLogoPath = null;
        if ($logoPath && file_exists(storage_path('app/public/' . $logoPath))) {
            $validLogoPath = storage_path('app/public/' . $logoPath);
        } elseif (file_exists(public_path('images/logo.jpg'))) {
            $validLogoPath = public_path('images/logo.jpg');
        }

        if ($validLogoPath) {
            $drawing->setPath($validLogoPath);
            $drawing->setHeight(80);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(10);
            $drawing->setWorksheet($sheet);
        }

        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(30);
        $sheet->getRowDimension(3)->setRowHeight(30);

        $rowIndex = 1;

        // Company Name
        $sheet->mergeCells("A{$rowIndex}:{$highestCol}{$rowIndex}");
        $sheet->setCellValue("A{$rowIndex}", $companyName);
        $sheet->getStyle("A{$rowIndex}")->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF1A237E');
        $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_BOTTOM);
        $rowIndex++;

        // Title
        $sheet->mergeCells("A{$rowIndex}:{$highestCol}{$rowIndex}");
        $sheet->setCellValue("A{$rowIndex}", $reportTitle);
        $sheet->getStyle("A{$rowIndex}")->getFont()->setBold(true)->setSize(18)->getColor()->setARGB('FF000000');
        $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $rowIndex++;

        // Subtitle
        $sheet->mergeCells("A{$rowIndex}:{$highestCol}{$rowIndex}");
        $sheet->setCellValue("A{$rowIndex}", $reportSubtitle);
        $sheet->getStyle("A{$rowIndex}")->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF424242');
        $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);
        $rowIndex++;

        // Period
        $sheet->mergeCells("A{$rowIndex}:{$highestCol}{$rowIndex}");
        $sheet->setCellValue("A{$rowIndex}", $periodLabel);
        $sheet->getStyle("A{$rowIndex}")->getFont()->setSize(10)->setItalic(true)->getColor()->setARGB('FF1A237E');
        $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $rowIndex += 2;

        // Header Style
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'name' => $excelFont, 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1A237E']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF3F51B5'],
                ],
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF1A237E']],
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF1A237E']],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ];

        // Header Row
        $sheet->setCellValue("A{$rowIndex}", "ACCOUNT DESCRIPTION");
        $sheet->getStyle("A{$rowIndex}")->applyFromArray($headerStyle);
        $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        $cIdx = 2;
        foreach ($currencies as $curr) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $sheet->setCellValue("{$colLetter}{$rowIndex}", "AMOUNT ({$curr})");
            $sheet->getStyle("{$colLetter}{$rowIndex}")->applyFromArray($headerStyle);
            $sheet->getStyle("{$colLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $cIdx++;
        }
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
        $sheet->setCellValue("{$colLetter}{$rowIndex}", "TOTAL (USD)");
        $sheet->getStyle("{$colLetter}{$rowIndex}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$colLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $sheet->getRowDimension($rowIndex)->setRowHeight(30);
        $rowIndex++;

        // Section Style
        $sectionStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FF1A237E'], 'name' => $excelFont, 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE8EAF6']
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => 'FF1A237E'],
                ],
            ],
        ];

        // REVENUE SECTION
        $sheet->setCellValue("A{$rowIndex}", "Revenue");
        for ($i = 2; $i <= $highestColIndex + 1; $i++) {
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->setCellValue("{$cLetter}{$rowIndex}", "");
        }
        $sheet->getStyle("A{$rowIndex}:{$highestCol}{$rowIndex}")->applyFromArray($sectionStyle);
        $rowIndex++;

        $revenue = $data['revenue'] ?? [];
        foreach ($revenue as $item) {
            $amounts = $item['amounts'] ?? [];
            $sheet->setCellValue("A{$rowIndex}", "     " . ($item['label'] ?? ''));
            $cIdx = 2;
            foreach ($currencies as $curr) {
                $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
                $val = (float)($amounts[$curr] ?? 0);
                $sheet->setCellValue("{$cLetter}{$rowIndex}", $val);
                $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $cIdx++;
            }
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $tUsd = (float)($item['total_usd'] ?? 0);
            $sheet->setCellValue("{$cLetter}{$rowIndex}", $tUsd);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getFont()->getColor()->setARGB('FF1A237E');
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getFont()->setBold(true);
            $rowIndex++;
        }

        // Total Revenue
        $totalRevenue = $data['total_revenue'] ?? [];
        $sheet->setCellValue("A{$rowIndex}", "Total Revenue");
        $sheet->getStyle("A{$rowIndex}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rowIndex}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$rowIndex}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

        $cIdx = 2;
        foreach ($currencies as $curr) {
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $val = (float)($totalRevenue[$curr] ?? 0);
            $sheet->setCellValue("{$cLetter}{$rowIndex}", $val);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
            $cIdx++;
        }
        $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
        $gTUsd = (float)($data['grand_total_revenue_usd'] ?? 0);
        $sheet->setCellValue("{$cLetter}{$rowIndex}", $gTUsd);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
        $rowIndex += 2;

        // EXPENSES SECTION
        $sheet->setCellValue("A{$rowIndex}", "Expenses");
        for ($i = 2; $i <= $highestColIndex + 1; $i++) {
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->setCellValue("{$cLetter}{$rowIndex}", "");
        }
        $sheet->getStyle("A{$rowIndex}:{$highestCol}{$rowIndex}")->applyFromArray($sectionStyle);
        $rowIndex++;

        $expenses = $data['expenses'] ?? [];
        foreach ($expenses as $item) {
            $amounts = $item['amounts'] ?? [];
            $sheet->setCellValue("A{$rowIndex}", "     " . ($item['label'] ?? ''));
            $cIdx = 2;
            foreach ($currencies as $curr) {
                $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
                $val = (float)($amounts[$curr] ?? 0);
                $sheet->setCellValue("{$cLetter}{$rowIndex}", $val);
                $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $cIdx++;
            }
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $tUsd = (float)($item['total_usd'] ?? 0);
            $sheet->setCellValue("{$cLetter}{$rowIndex}", $tUsd);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getFont()->getColor()->setARGB('FF1A237E');
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getFont()->setBold(true);
            $rowIndex++;
        }

        // Total Expenses
        $totalExpenses = $data['total_expenses'] ?? [];
        $sheet->setCellValue("A{$rowIndex}", "Total Expenses");
        $sheet->getStyle("A{$rowIndex}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$rowIndex}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$rowIndex}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

        $cIdx = 2;
        foreach ($currencies as $curr) {
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $val = (float)($totalExpenses[$curr] ?? 0);
            $sheet->setCellValue("{$cLetter}{$rowIndex}", $val);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
            $cIdx++;
        }
        $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
        $gTUsd = (float)($data['grand_total_expenses_usd'] ?? 0);
        $sheet->setCellValue("{$cLetter}{$rowIndex}", $gTUsd);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
        $rowIndex += 2;

        // NET INCOME
        $netIncomeMap = $data['net_income'] ?? [];
        $gNetUSD = (float)($data['grand_net_income_usd'] ?? 0);

        $netIncomeStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 12],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1A237E']
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => 'FF1A237E'],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['argb' => 'FF1A237E'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];

        $sheet->setCellValue("A{$rowIndex}", "Net Income");
        $sheet->getStyle("A{$rowIndex}")->applyFromArray($netIncomeStyle);

        $cIdx = 2;
        foreach ($currencies as $curr) {
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $val = (float)($netIncomeMap[$curr] ?? 0);
            $sheet->setCellValue("{$cLetter}{$rowIndex}", $val);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->applyFromArray($netIncomeStyle);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');
            $cIdx++;
        }

        $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
        $sheet->setCellValue("{$cLetter}{$rowIndex}", $gNetUSD);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->applyFromArray($netIncomeStyle);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getFont()->getColor()->setARGB($gNetUSD >= 0 ? 'FF1B5E20' : 'FFC62828');
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("{$cLetter}{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0.00');

        $sheet->getRowDimension($rowIndex)->setRowHeight(30);
        $rowIndex += 2;

        // KPI SUMMARY
        $totalRev = (float)($data['grand_total_revenue_usd'] ?? 0);
        $totalExp = (float)($data['grand_total_expenses_usd'] ?? 0);
        $netProfit = (float)($data['grand_net_income_usd'] ?? 0);
        $grossMargin = $totalRev > 0 ? (($totalRev - $totalExp) / $totalRev) * 100 : 0;
        $netMargin = $totalRev > 0 ? ($netProfit / $totalRev) * 100 : 0;
        $opRatio = $totalRev > 0 ? ($totalExp / $totalRev) * 100 : 0;

        $pct = function(float $v) { return number_format($v, 1) . '%'; };

        $sheet->mergeCells("A{$rowIndex}:{$highestCol}{$rowIndex}");
        $sheet->setCellValue("A{$rowIndex}", 'Financial KPI Summary');
        $sheet->getStyle("A{$rowIndex}:{$highestCol}{$rowIndex}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF1A237E'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE1F5FE']],
        ]);
        $rowIndex++;

        // KPI Header
        $sheet->setCellValue("A{$rowIndex}", 'KPI');
        $sheet->setCellValue("B{$rowIndex}", 'Value');
        $sheet->getStyle("A{$rowIndex}:{$highestCol}{$rowIndex}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF1A237E'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF5F5F5']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF1A237E']]],
        ]);
        $sheet->getStyle("B{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($rowIndex)->setRowHeight(22);
        $rowIndex++;

        $kpis = [
            ['Total Revenue', number_format($totalRev, 2)],
            ['Total Expense', number_format($totalExp, 2)],
            ['Gross Profit Margin', $pct($grossMargin)],
            ['Net Profit Margin', $pct($netMargin)],
            ['Operation Ratio', $pct($opRatio)],
            ['Net Profit', number_format($netProfit, 2)],
        ];

        foreach ($kpis as $k => $kpi) {
            $isLast = $k === count($kpis) - 1;
            $sheet->setCellValue("A{$rowIndex}", $kpi[0]);
            $sheet->setCellValue("B{$rowIndex}", $kpi[1]);
            
            $rowStyle = [
                'font' => [
                    'size' => 9, 
                    'bold' => $isLast, 
                    'color' => ['argb' => $isLast ? 'FF1B5E20' : 'FF000000']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID, 
                    'startColor' => ['argb' => $isLast ? 'FFE8F5E9' : 'FFFFFFFF']
                ],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E0E0']]
                ]
            ];
            $sheet->getStyle("A{$rowIndex}:{$highestCol}{$rowIndex}")->applyFromArray($rowStyle);
            
            if ($isLast) {
                $valColor = $netProfit >= 0 ? 'FF1B5E20' : 'FFC62828';
                $sheet->getStyle("B{$rowIndex}")->getFont()->getColor()->setARGB($valColor);
            }
            $sheet->getStyle("B{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $rowIndex++;
        }

        $rowIndex += 2;

        // APPROVAL SECTION
        $today = date('d-M-Y');
        $sheet->mergeCells("A{$rowIndex}:{$highestCol}{$rowIndex}");
        $sheet->setCellValue("A{$rowIndex}", 'Approval');
        $sheet->getStyle("A{$rowIndex}")->getFont()->setBold(true)->setSize(11);
        $rowIndex++;

        $approvalTitles = ['Prepared', 'Checked By', 'Approved By'];
        $approvalNames = ['Finance Officer', 'Accounting Manager', 'General Manager'];

        $colSpan = max(1, floor(($highestColIndex) / 3));
        
        $c1Start = 1;
        $c1End = $c1Start + $colSpan - 1;
        $c2Start = $c1End + 1;
        $c2End = $c2Start + $colSpan - 1;
        $c3Start = $c2End + 1;
        $c3End = $highestColIndex + 1;

        $cols = [
            ['start' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c1Start), 'end' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c1End)],
            ['start' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c2Start), 'end' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c2End)],
            ['start' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c3Start), 'end' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c3End)],
        ];

        // Titles
        for ($i = 0; $i < 3; $i++) {
            $range = $cols[$i]['start'] . $rowIndex . ':' . $cols[$i]['end'] . $rowIndex;
            $sheet->mergeCells($range);
            $sheet->setCellValue($cols[$i]['start'] . $rowIndex, $approvalTitles[$i]);
            $sheet->getStyle($range)->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $rowIndex++;

        // Blank lines
        for ($i = 0; $i < 3; $i++) {
            $range = $cols[$i]['start'] . $rowIndex . ':' . $cols[$i]['end'] . $rowIndex;
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle($range)->getBorders()->getBottom()->getColor()->setARGB('FF9E9E9E');
        }
        $sheet->getRowDimension($rowIndex)->setRowHeight(35);
        $rowIndex++;

        // Names
        for ($i = 0; $i < 3; $i++) {
            $range = $cols[$i]['start'] . $rowIndex . ':' . $cols[$i]['end'] . $rowIndex;
            $sheet->mergeCells($range);
            $sheet->setCellValue($cols[$i]['start'] . $rowIndex, $approvalNames[$i]);
            $sheet->getStyle($range)->getFont()->setSize(9);
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $rowIndex++;

        // Date
        for ($i = 0; $i < 3; $i++) {
            $range = $cols[$i]['start'] . $rowIndex . ':' . $cols[$i]['end'] . $rowIndex;
            $sheet->mergeCells($range);
            $sheet->setCellValue($cols[$i]['start'] . $rowIndex, $today);
            $sheet->getStyle($range)->getFont()->setSize(8)->getColor()->setARGB('FF757575');
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Income_Statement_Report_' . date('Ymd') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
