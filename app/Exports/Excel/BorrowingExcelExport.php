<?php

namespace App\Exports\Excel;

use App\Models\Setting;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BorrowingExcelExport
{
    public function download(array $data, Request $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setShowGridlines(false);

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont)->setSize(9);

        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? '';
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? '';
        $reportTitle = "Borrowing Report";

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 9],
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

        $headers = [
            'No.',
            'Date',
            'Account Code',
            'Lender Code',
            'Name',
            'Type',
            'Payment Method',
            'Currency',
            'Term',
            'Loan Amount',
            'Interest Rate (%)',
            'Fee',
            'Maturity Date',
            'S/L Term',
            'Balance',
            'Late Principal'
        ];

        $highestCol = "P";

        $sheet->getRowDimension(1)->setRowHeight(45);

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

        $keys = [
            'borrowing_date',
            'account_no',
            'lender_code',
            'lender_name',
            'lender_type',
            'payment_method',
            'currency',
            'term_months',
            'amount',
            'interest_rate',
            'fee',
            'maturity_date',
            'sl_term',
            'balance',
            'late_principal'
        ];

        $numberKeys = [
            'amount',
            'interest_rate',
            'fee',
            'balance',
            'late_principal'
        ];

        $integerKeys = [
            'term_months'
        ];

        $row = 6;

        $colIndex = 1;
        foreach ($headers as $header) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($columnLetter . $row, $header);
            $colIndex++;
        }
        $sheet->getStyle('A' . $row . ':' . $highestCol . $row)->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(30);
        $row++;

        $totals = array_fill_keys($keys, 0);

        $no = 1;
        foreach ($data as $item) {
            $colIndex = 1;

            // No.
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++);
            $sheet->setCellValue($columnLetter . $row, $no++);
            $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            foreach ($keys as $key) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $value = $item[$key] ?? '';

                if (preg_match('/date$/i', $key) && !empty($value) && $value !== '-') {
                    try {
                        $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                    } catch (\Exception $e) {
                    }
                }

                if (in_array($key, $numberKeys)) {
                    $sheet->setCellValue($columnLetter . $row, (float) $value);
                    $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $totals[$key] += (float) $value;
                } elseif (in_array($key, $integerKeys)) {
                    $sheet->setCellValue($columnLetter . $row, (int) $value);
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $totals[$key] += (int) $value;
                } else {
                    $sheet->setCellValue($columnLetter . $row, $value);
                }

                $colIndex++;
            }
            $sheet->getStyle('A' . $row . ':' . $highestCol . $row)->applyFromArray($dataStyle);
            $row++;
        }

        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->getStyle('A' . $row . ':H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $colIndex = 9; // 'Term' starts at 9
        foreach ($keys as $key) {
            // Skip first 7 keys (Date to Currency)
            // They map to column 2 to 8
            // Term maps to 9
            // Wait, we merged A to H (columns 1 to 8). So next col is 9.
            // But we need to make sure we map correctly.
        }

        // Let's just hardcode the totals matching columns
        // 9: term (no total), 10: amount, 11: interest_rate (no total), 12: fee, 13: maturity_date, 14: sl_term, 15: balance, 16: late_principal
        $totalCols = [
            10 => $totals['amount'],
            12 => $totals['fee'],
            15 => $totals['balance'],
            16 => $totals['late_principal']
        ];

        foreach ($totalCols as $cIdx => $val) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $sheet->setCellValue($columnLetter . $row, $val);
            $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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

        $colIndex = 1;
        foreach ($headers as $header) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $width = strlen($header) * 1.1 + 4;
            $sheet->getColumnDimension($columnLetter)->setWidth($width < 12 ? 12 : $width);
            $colIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Borrowing_Report_' . date('Ymd') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
