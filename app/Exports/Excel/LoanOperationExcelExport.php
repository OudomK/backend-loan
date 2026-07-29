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

class LoanOperationExcelExport
{
    public function download($loans, Request $request)
    {
        $spreadsheet = new Spreadsheet();

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont);
        $spreadsheet->getDefaultStyle()->getFont()->setSize(9);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setShowGridlines(false);

        $highestCol = 'L'; // A to L is 12 columns

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

        $sheet->mergeCells("A1:{$highestCol}1");
        $khmerCompanyName = Setting::where('key', 'khmer_company_name')->value('value') ?? 'ប្រាក់.រហ័ស ហ្វាយនែន ម.ក';
        $sheet->setCellValue('A1', $khmerCompanyName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A2:{$highestCol}2");
        $companyName = Setting::where('key', 'company_name')->value('value') ?? 'Quick Fund Finance Plc.';
        $sheet->setCellValue('A2', $companyName);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A3:{$highestCol}3");
        $sheet->setCellValue('A3', 'Loan Operation');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Date Info
        $sheet->mergeCells("A5:B5");
        $sheet->setCellValue('A5', 'Date:');
        $sheet->mergeCells("C5:E5");
        $sheet->setCellValue('C5', date('d/m/Y'));
        $sheet->getStyle('A5')->getFont()->setBold(true);

        // Headers
        $headers = [
            "No.",
            "Code",
            "Name",
            "Cycle",
            "Amount",
            "Curr.",
            "Int. Rate",
            "Admin Fee",
            "Term",
            "Purpose",
            "Date Disb.",
            "Status"
        ];

        $row = 7;
        $sheet->getRowDimension($row)->setRowHeight(50);

        foreach ($headers as $index => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($colLetter . $row, $header);

            // Header Styling
            $sheet->getStyle($colLetter . $row)->getFont()->setBold(true);
            $sheet->getStyle($colLetter . $row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $sheet->getStyle($colLetter . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFD3D3D3');

            // Column widths
            $width = 12;
            if ($index == 0)
                $width = 6;  // No
            if ($index == 1)
                $width = 15; // Code
            if ($index == 2)
                $width = 15; // Name
            if ($index == 3)
                $width = 8;  // Cycle
            if ($index == 4)
                $width = 15; // Amount
            if ($index == 5)
                $width = 8;  // Curr
            if ($index == 6)
                $width = 10;  // Int Rate
            if ($index == 7)
                $width = 12;  // Admin Fee
            if ($index == 8)
                $width = 8;  // Term
            if ($index == 9)
                $width = 20; // Purpose
            if ($index == 10)
                $width = 15; // Date Disb.
            if ($index == 11)
                $width = 15; // Status

            $sheet->getColumnDimension($colLetter)->setWidth($width);

            // Apply border
            $sheet->getStyle($colLetter . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        // Data Rows
        $row = 8;
        $no = 1;

        foreach ($loans as $loan) {
            $borrower = $loan->borrower;
            $borrowerName = $borrower ? ($borrower->khmer_name ?: trim($borrower->first_name . ' ' . $borrower->last_name)) : 'N/A';

            // Format data
            $interestRate = $loan->interest_rate . '%';
            $adminFee = $loan->admin_fee . '%';
            $term = $loan->duration_months . 'm';

            $dateDisb = '-';
            if ($loan->start_date) {
                $dateDisb = \Carbon\Carbon::parse($loan->start_date)->format('d/m/Y');
            }

            $loanCode = $loan->loan_code;
            if ($loanCode) {
                $loanCode = str_ireplace(['-Refinanced', '-Rescheduled'], ['-RF', '-RS'], $loanCode);
            }

            $purpose = $loan->purpose ?? 'N/A';
            if ($purpose !== 'N/A') {
                $purpose = str_ireplace(['Refinance', 'Reschedule'], ['RF', 'RS'], $purpose);
            }

            $rowData = [
                $no++,
                $loanCode,
                $borrowerName,
                $loan->cycle ?? 1,
                $loan->amount,
                $loan->currency,
                $interestRate,
                $adminFee,
                $term,
                $purpose,
                $dateDisb,
                strtoupper($loan->status)
            ];

            foreach ($rowData as $index => $value) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);

                if ($index == 4) { // Amount
                    $sheet->setCellValueExplicit($colLetter . $row, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    $sheet->getStyle($colLetter . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                } else {
                    $sheet->setCellValue($colLetter . $row, $value);
                    if (in_array($index, [0, 3, 5, 6, 7, 8, 10, 11])) { // Center alignment for these cols
                        $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                $sheet->getStyle($colLetter . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle($colLetter . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
            $sheet->getRowDimension($row)->setRowHeight(22);
            $row++;
        }

        // Output to browser
        $writer = new Xlsx($spreadsheet);
        $fileName = "Loan_Operation_Activity_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
