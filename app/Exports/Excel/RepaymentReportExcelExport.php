<?php

namespace App\Exports\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Http\Request;
use App\Models\Setting;

class RepaymentReportExcelExport
{
    public function download(array $data, Request $request)
    {
        $spreadsheet = new Spreadsheet();
        
        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont);
        $spreadsheet->getDefaultStyle()->getFont()->setSize(8);
        
        $sheet = $spreadsheet->getActiveSheet();
        
        // Hide default gridlines to remove unrelated borders
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

        // Title and Header Information
        $sheet->getRowDimension(1)->setRowHeight(45); // Space for taller logo
        
        $highestCol = 'N'; // Merge only up to column N so it's visible on the first screen
        
        $sheet->mergeCells("A1:{$highestCol}1");
        $sheet->setCellValue('A1', 'ប្រាក់.រហ័ស ហ្វាយនែន ម.ក');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A2:{$highestCol}2");
        $sheet->setCellValue('A2', 'Quick Fund Finance Plc.');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A3:{$highestCol}3");
        $sheet->setCellValue('A3', 'Repayment Report');
        $sheet->getStyle('A3')->getFont()->setSize(11);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $dateStr = '';
        if ($fromDate && $toDate) {
            $dateStr = 'From ' . date('d/m/Y', strtotime($fromDate)) . ' To ' . date('d/m/Y', strtotime($toDate));
        } else {
            $dateStr = 'As At ' . date('d/m/Y');
        }

        $sheet->mergeCells("A4:{$highestCol}4");
        $sheet->setCellValue('A4', $dateStr . ', Exchange Rate 4000');
        $sheet->getStyle('A4')->getFont()->setSize(10);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Removed Summary Information (Currency, Count)
        
        // Headers
        $headers = [
            "No", "Payment Date", "Disb. Date", "Loan Code", "Name", "Village", "Commune", "District", 
            "Province", "CoBorrower Name", "CoBorrower Tel", "Guarantor Name", "Guarantor Tel", "Disb. Amount", 
            "Currency", "Rate", "Monthly Interest Rate", "Term", "Tenor", "Payment Method", 
            "Re-Finance", "Admin Fee", "Re-Finance Fee", "Product", "Collateral", "C.O Disburse", "C.O Repay", 
            "Principal Paid", "Interest Paid", "Penalty Paid", "Paid-Off Paid", "Recovery", "Prepayment", 
            "Withd Prepayment", "Total Paid", "Type of Payment", "Fee Paid", "Payment Status"
        ];
        
        $columnKeys = [
            "payment_date", "disb_date", "loan_no", "name", "village", "commune", "district", 
            "province", "coborrower_name", "coborrower_tel", "guarantor_name", "guarantor_tel", "disb_amount", 
            "currency", "rate", "monthly_interest_rate", "term", "tenor", "payment_method", 
            "re_finance", "admin_fee", "re_finance_fee", "product_name", "collateral_type", "co_disburse", "co_repay", 
            "principal_paid", "interest_paid", "penalty_paid", "paid_off_paid", "recovery", "prepayment", 
            "withd_prepayment", "total_paid", "type_of_payment", "fee_paid", "payment_status"
        ];

        // Format Headers
        $row = 7;
        $sheet->getRowDimension($row)->setRowHeight(50); // Enlarge header row height

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }

        // Header Styling
        $headerRange = 'A7:' . $sheet->getHighestColumn() . '7';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D3D3D3']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Insert Data
        $row = 8;
        $totals = [
            'principal_paid' => 0,
            'interest_paid' => 0,
            'penalty_paid' => 0,
            'total_paid' => 0,
            'total_balance' => 0,
            'total_p_balance' => 0,
            'total_i_balance' => 0,
            're_finance' => 0,
            'admin_fee' => 0,
        ];

        $no = 1;
        foreach ($data as $item) {
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $no++);
            
            foreach ($columnKeys as $key) {
                $value = $item[$key] ?? '-';
                
                // Capitalize the first letter for payment status
                if ($key === 'payment_status' && is_string($value)) {
                    $value = ucfirst($value);
                }
                
                // Format dates as DD/MM/YYYY
                if (in_array($key, ['payment_date', 'disb_date']) && $value !== '-' && !empty($value)) {
                    $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                }
                
                // Keep numbers as numbers
                if (is_numeric($value)) {
                    $sheet->setCellValueExplicit($col . $row, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValue($col . $row, $value);
                }

                // Add to totals
                if (array_key_exists($key, $totals)) {
                    $valNum = (float) str_replace(',', '', $value);
                    $totals[$key] += $valNum;
                }
                
                $col++;
            }
            $row++;
        }

        // Summary Totals Row
        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->mergeCells('A' . $row . ':AB' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->setCellValue('AC' . $row, $totals['principal_paid']);
        $sheet->setCellValue('AD' . $row, $totals['interest_paid']);
        $sheet->setCellValue('AE' . $row, $totals['penalty_paid']);
        $sheet->setCellValue('AJ' . $row, $totals['total_paid']);

        // Format Total Row
        $totalRange = 'A' . $row . ':' . $sheet->getHighestColumn() . $row;
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // Apply borders to all data cells
        if ($row > 8) {
            $dataRange = 'A8:' . $sheet->getHighestColumn() . ($row - 1);
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);
        }

        // Auto-size columns
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        // Output to browser
        $writer = new Xlsx($spreadsheet);
        $fileName = "Repayment_Report_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
