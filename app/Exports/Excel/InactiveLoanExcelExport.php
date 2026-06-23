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

class InactiveLoanExcelExport
{
    public function download(array $data, Request $request, ?string $fromDateStr, ?string $toDateStr, ?string $officerName)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inactive Loan');
        
        // Hide default gridlines
        $sheet->setShowGridlines(false);

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont)->setSize(8);

        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? "ប្រាក់ រហ័ស ហ្វាយនែន ម.ក";
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? "Quick Fund Finance Plc.";
        $reportTitle = "Inactive Loan Listing Report by Period";

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

        $sheet->getRowDimension(1)->setRowHeight(45); // Space for taller logo

        $headers = [
            'Disb. Date', 'Loan No.', 'Name', 'Product Name', 'Village', 'Commune', 'District', 'Province',
            'Disb. Amount', 'Currency', 'Interest Rate', 'Processing Fee', 'Monthly Interest Rate', 
            'Term', 'Tenor', 'Pay. Method', 'Re-Finance', 'Restructure', 'Admin Fee', 'Refinance Fee', 
            'Collateral Type', 'C.O Disburse', 'C.O Repay.', 'Outstanding', 'Principle Paid', 'Interest Paid', 
            'Inactive Date', 'Write-Off'
        ];

        $highestCol = 'N';

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
        
        $filterInfo = "Period: $fDate - $tDate";
        if (!$fromDateStr) {
            $filterInfo = "As At: $tDate";
        }
        $filterInfo .= " , CO: $officerName";
        
        $sheet->mergeCells("A4:{$highestCol}4");
        $sheet->setCellValue('A4', $filterInfo);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4')->getFont()->setSize(10);
        
        $keys = [
            'disbursement_date', 'loan_code', 'client_name', 'loan_product', 'village_name', 'commune_name', 'district_name', 'province_name',
            'disbursement_amount', 'currency_code', 'interest_rate', 'processing_fee', 'monthly_interest_rate', 'term', 'tenor',
            'payment_method', 'refinance_amount', 'restructure', 'admin_fee', 'refinance_fee', 'collateral_type',
            'co_disburse', 'co_repay', 'outstanding_amount', 'principal_paid', 'interest_paid', 'inactive_date', 'write_off_amount'
        ];

        $numberKeys = [
            'disbursement_amount', 'interest_rate', 'processing_fee', 'monthly_interest_rate', 'refinance_amount',
            'admin_fee', 'refinance_fee', 'outstanding_amount', 'principal_paid', 'interest_paid', 'write_off_amount'
        ];
        
        $integerKeys = [
            'term'
        ];
        
        $centeredKeys = [
            'disbursement_date', 'loan_code', 'currency_code', 'interest_rate', 'term', 'tenor'
        ];

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
                $value = $item[$key] ?? '';

                if (preg_match('/date$/i', $key) && !empty($value) && $value !== '-') {
                    try {
                        $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                    } catch (\Exception $e) {}
                }

                if (in_array($key, $numberKeys)) {
                    $sheet->setCellValue($columnLetter . $row, (float)$value);
                    $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $totals[$key] += (float)$value;
                } elseif (in_array($key, $integerKeys)) {
                    $sheet->setCellValue($columnLetter . $row, (int)$value);
                    $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $totals[$key] += (int)$value;
                } else {
                    if ($key === 'payment_method') {
                        $value = \App\Support\FormatHelper::formatPaymentMethod((string) $value);
                    }
                    $sheet->setCellValue($columnLetter . $row, $value);
                    if (in_array($key, $centeredKeys)) {
                        $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }
                
                $colIndex++;
            }
            $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->applyFromArray($dataStyle);
            $row++;
        }

        // Total Row
        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row . ':G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $colIndex = 1;
        foreach ($keys as $key) {
            if (in_array($key, $numberKeys)) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($columnLetter . $row, $totals[$key]);
                $sheet->getStyle($columnLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle($columnLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $colIndex++;
        }
        
        $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->applyFromArray([
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

        // Auto-size columns based on headers (avoiding huge columns)
        $colIndex = 1;
        foreach ($headers as $header) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $width = strlen($header) * 1.1 + 4;
            $sheet->getColumnDimension($columnLetter)->setWidth($width < 12 ? 12 : $width);
            $colIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Inactive_Loan_Report_' . date('Ymd_His') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
