<?php

namespace App\Exports\Excel;

use App\Models\Setting;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DisbursementExcelExport
{
    public function download(array $data, Request $request, ?string $fromDateStr, ?string $toDateStr, ?string $officerName)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Hide default gridlines
        $sheet->setShowGridlines(false);

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont)->setSize(8);

        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? '';
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? '';
        $reportTitle = "Disbursement Report (CO Productivity)";

        // Styles
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

        $sheet->getRowDimension(1)->setRowHeight(45);

        $highestCol = 'N'; // Adjust title merge span so it visually matches the gap in other reports

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

        $fDate = $fromDateStr ? \Carbon\Carbon::parse($fromDateStr)->format('d/m/Y') : "";
        $tDate = $toDateStr ? \Carbon\Carbon::parse($toDateStr)->format('d/m/Y') : "";
        $filterInfo = "Period: $fDate - $tDate , CO: $officerName";
        $sheet->mergeCells("A4:{$highestCol}4");
        $sheet->setCellValue('A4', $filterInfo);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4')->getFont()->setSize(10);

        $headers = [
            'Code',
            'CO Name',
            'Disb Old',
            'Disb New',
            'Disb Total',
            'Amt Old',
            'Amt New',
            'Amt Total',
            'Total Client',
            'Loan OS',
            'Interest OS',
            'Fee OS',
            'Principal Coll.',
            'Interest Coll.',
            'Fee Coll.',
            'Penalty Coll.',
            'Paid-off Coll.',
            'Recovery',
            'PAR1 No.',
            'PAR1 Amt',
            'PAR1 %',
            'PAR1-29 No.',
            'PAR1-29 Amt',
            'PAR1-29 %',
            'PAR30+ No.',
            'PAR30+ Amt',
            'PAR30+ %',
            'Principal Due',
            'Interest Due',
            'Rep. Rate',
            'WO No.',
            'WO Amt',
            'WO YTD No.',
            'WO YTD Amt'
        ];

        $keys = [
            'co_code',
            'co_name',
            'no_disb_old',
            'no_disb_new',
            'no_disb_total',
            'amt_disb_old',
            'amt_disb_new',
            'amt_disb_total',
            'total_client',
            'loan_os',
            'interest_os',
            'fee_os',
            'principal_collected',
            'interest_collected',
            'fee_collected',
            'penalty_collected',
            'paid_off_collected',
            'recovery',
            'par1_count',
            'par1_amount',
            'par1_percent',
            'par1_29_count',
            'par1_29_amount',
            'par1_29_percent',
            'par30_count',
            'par30_amount',
            'par30_percent',
            'principal_due',
            'interest_due',
            'repayment_rate',
            'wo_cur_count',
            'wo_cur_principal',
            'wo_ytd_count',
            'wo_ytd_principal'
        ];

        $percentKeys = ['par1_percent', 'par1_29_percent', 'par30_percent', 'repayment_rate'];
        $integerKeys = ['no_disb_old', 'no_disb_new', 'no_disb_total', 'total_client', 'par1_count', 'par1_29_count', 'par30_count', 'wo_cur_count', 'wo_ytd_count'];

        $row = 7;

        // Table Headers
        $colIndex = 1;
        foreach ($headers as $header) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($columnLetter . $row, $header);
            $colIndex++;
        }
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(50); // Make header taller
        $row++;

        // Data Rows
        $totals = array_fill_keys($keys, 0);

        foreach ($data as $item) {
            $colIndex = 1;
            foreach ($keys as $key) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $value = $item[$key] ?? 0;

                if ($key === 'co_code' || $key === 'co_name') {
                    $sheet->setCellValueExplicit($columnLetter . $row, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal($key === 'co_code' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT);
                } else if (in_array($key, $percentKeys)) {
                    $valNum = (float) $value;
                    $sheet->setCellValue($columnLetter . $row, $valNum / 100);
                    $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('0.00%');
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else if (in_array($key, $integerKeys)) {
                    $valNum = (int) $value;
                    $totals[$key] += $valNum;
                    $sheet->setCellValueExplicit($columnLetter . $row, $valNum, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $valNum = (float) $value;
                    $totals[$key] += $valNum;
                    $sheet->setCellValueExplicit($columnLetter . $row, $valNum, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $colIndex++;
            }
            $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->applyFromArray($dataStyle);
            $row++;
        }

        // Totals Row
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        $colIndex = 3; // Start from 'Disb Old' (C column)
        for ($i = 2; $i < count($keys); $i++) {
            $key = $keys[$i];
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

            if (in_array($key, $percentKeys)) {
                // Compute percent safely
                $val = 0;
                if ($key === 'par1_percent') {
                    $val = ($totals['loan_os'] ?? 0) > 0 ? ($totals['par1_amount'] / $totals['loan_os']) : 0;
                } else if ($key === 'par1_29_percent') {
                    $val = ($totals['loan_os'] ?? 0) > 0 ? ($totals['par1_29_amount'] / $totals['loan_os']) : 0;
                } else if ($key === 'par30_percent') {
                    $val = ($totals['loan_os'] ?? 0) > 0 ? ($totals['par30_amount'] / $totals['loan_os']) : 0;
                } else if ($key === 'repayment_rate') {
                    $val = ($totals['principal_due'] ?? 0) > 0 ? ($totals['principal_collected'] / $totals['principal_due']) : 0;
                }
                $sheet->setCellValue($columnLetter . $row, $val);
                $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } else if (in_array($key, $integerKeys)) {
                $sheet->setCellValueExplicit($columnLetter . $row, $totals[$key], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } else {
                $sheet->setCellValueExplicit($columnLetter . $row, $totals[$key], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $sheet->getStyle($columnLetter . $row)->getFont()->setBold(true);
            $colIndex++;
        }

        $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->applyFromArray($headerStyle);
        $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDFF1EC'); // Light teal like Flutter '#DFF1EC'

        // Auto-size columns
        for ($c = 1; $c <= count($headers); $c++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        // Output to browser
        $writer = new Xlsx($spreadsheet);
        $fileName = "Disbursement_Report_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
