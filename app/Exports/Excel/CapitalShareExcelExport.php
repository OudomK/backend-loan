<?php

namespace App\Exports\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\Setting;

class CapitalShareExcelExport
{
    public function download(array $data, \Illuminate\Http\Request $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setShowGridlines(false);

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';

        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont);
        $spreadsheet->getDefaultStyle()->getFont()->setSize(9);

        $khmerCompanyName = Setting::where('key', 'company_name_khmer')->value('value') ?? 'ប្រាក់ រហ័ស ហ្វាយនែន ម.ក';
        $englishCompanyName = Setting::where('key', 'company_name_english')->value('value') ?? 'Quick Fund Finance Plc.';
        $reportTitle = 'Capital & Share Report';

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'name' => $excelFont, 'size' => 9],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D0CECE']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ];

        $dataStyle = [
            'font' => ['name' => $excelFont, 'size' => 9],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ];

        $headers = [
            'No.',
            'Date',
            'Account Code',
            'Holder Code',
            'Name',
            'Type',
            'Category',
            'Currency',
            'Share Qty',
            'Invested Amount',
            'Balance',
            'Dividends',
            'Total Dividend Paid',
            'Last Dividend Date',
        ];

        $highestCol = "N"; // 14 columns

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
            $drawing->setHeight(90);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
        }

        $sheet->getRowDimension(1)->setRowHeight(45); // Space for taller logo

        $sheet->mergeCells("A1:{$highestCol}1");
        $sheet->setCellValue('A1', $khmerCompanyName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A2:{$highestCol}2");
        $sheet->setCellValue('A2', $englishCompanyName);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A3:{$highestCol}3");
        $sheet->setCellValue('A3', $reportTitle);
        $sheet->getStyle('A3')->getFont()->setSize(11);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $search = $request->query('search');
        if ($search) {
            $sheet->mergeCells("A4:{$highestCol}4");
            $sheet->setCellValue('A4', "Search: $search");
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A4')->getFont()->setSize(10);
        }

        $row = 6;

        // Group by currency
        $grouped = [];
        foreach ($data as $item) {
            $currency = $item['currency'] ?? 'USD';
            $grouped[$currency][] = $item;
        }

        ksort($grouped);

        if (empty($grouped)) {
            $sheet->mergeCells("A{$row}:{$highestCol}{$row}");
            $sheet->setCellValue("A{$row}", 'No data available');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach ($grouped as $currency => $items) {
            $sheet->mergeCells("A{$row}:{$highestCol}{$row}");
            $sheet->setCellValue("A{$row}", 'Currency: ' . $currency);
            $sheet->getStyle("A{$row}:{$highestCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'name' => $excelFont, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DCEEFF']
                ],
            ]);
            $row++;

            // Headers
            $colIndex = 1;
            foreach ($headers as $header) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($columnLetter . $row, $header);
                $colIndex++;
            }
            $sheet->getStyle('A' . $row . ':' . $highestCol . $row)->applyFromArray($headerStyle);
            $sheet->getRowDimension($row)->setRowHeight(30);
            $row++;

            $totalShareQty = 0;
            $totalAmount = 0.0;
            $totalBalance = 0.0;
            $totalDividends = 0.0;
            $totalDividendPaid = 0.0;

            $no = 1;
            foreach ($items as $item) {
                $isRealCapital = ($item['category'] ?? '') === 'Real Capital';
                $shareQty = (int) ($item['share_qty'] ?? 0);
                $amount = (float) ($item['amount'] ?? 0);
                $balance = (float) ($item['balance'] ?? 0);
                $dividends = $isRealCapital ? (float) ($item['dividends'] ?? 0) : 0.0;
                $dividendPaid = $isRealCapital ? (float) ($item['total_dividend_paid'] ?? 0) : 0.0;

                $totalShareQty += $shareQty;
                $totalAmount += $amount;
                $totalBalance += $balance;
                $totalDividends += $dividends;
                $totalDividendPaid += $dividendPaid;

                $date = $item['borrowing_date'] ?? $item['created_at'] ?? '';
                if (!empty($date)) {
                    try {
                        $date = \Carbon\Carbon::parse($date)->format('d/m/Y');
                    } catch (\Exception $e) {}
                }

                $lastDivDate = $item['last_dividend_date'] ?? '';
                if (!empty($lastDivDate)) {
                    try {
                        $lastDivDate = \Carbon\Carbon::parse($lastDivDate)->format('d/m/Y');
                    } catch (\Exception $e) {}
                }

                $rowData = [
                    $no++,
                    $date,
                    $item['account_no'] ?? '-',
                    $item['lender_code'] ?? '-',
                    ($item['investor_name'] ?? $item['lender_name'] ?? '-'),
                    $item['lender_type'] ?? '-',
                    $item['category'] ?? '-',
                    $currency,
                    $shareQty,
                    $amount,
                    $balance,
                    $isRealCapital ? $dividends : '-',
                    $isRealCapital ? $dividendPaid : '-',
                    $lastDivDate
                ];

                $colIndex = 1;
                foreach ($rowData as $val) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    
                    if (in_array($colIndex, [10, 11, 12, 13]) && is_numeric($val)) { // Amount, Balance, Dividends, Paid
                        $sheet->setCellValue($columnLetter . $row, (float) $val);
                        $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                        $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } elseif ($colIndex === 9) { // Share Qty
                        $sheet->setCellValue($columnLetter . $row, (int) $val);
                        $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    } else {
                        $sheet->setCellValue($columnLetter . $row, $val);
                    }
                    $colIndex++;
                }

                $sheet->getStyle('A' . $row . ':' . $highestCol . $row)->applyFromArray($dataStyle);
                $row++;
            }

            // Totals
            $sheet->setCellValue('A' . $row, 'Total');
            $sheet->mergeCells('A' . $row . ':H' . $row);
            $sheet->getStyle('A' . $row . ':H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $totalCols = [
                9 => $totalShareQty,
                10 => $totalAmount,
                11 => $totalBalance,
                12 => $totalDividends,
                13 => $totalDividendPaid,
            ];

            foreach ($totalCols as $cIdx => $val) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
                $sheet->setCellValue($columnLetter . $row, $val);
                if ($cIdx === 9) {
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }

            $sheet->getStyle('A' . $row . ':' . $highestCol . $row)->applyFromArray([
                'font' => ['bold' => true, 'name' => $excelFont, 'size' => 9],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ]
            ]);

            $row += 2; // spacing between currencies
        }

        // Adjust column widths
        $widths = [8, 12, 16, 16, 24, 16, 16, 10, 10, 16, 16, 16, 16, 16];
        $colIndex = 1;
        foreach ($widths as $w) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setWidth($w);
            $colIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Capital_Share_Report_' . date('Ymd') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
