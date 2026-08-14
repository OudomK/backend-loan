<?php

namespace App\Exports\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Http\Request;
use App\Models\Setting;

class PaymentHistoryExcelExport
{
    public function download(array $historyData, string $loanCode, string $customerName, string $currency, Request $request)
    {
        $spreadsheet = new Spreadsheet();
        
        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont);
        $spreadsheet->getDefaultStyle()->getFont()->setSize(9);
        
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setShowGridlines(false);
        
        $highestCol = 'M'; // A to M is 13 columns
        
        // Title and Header Information
        $sheet->getRowDimension(1)->setRowHeight(45);
        
        $sheet->mergeCells("A1:{$highestCol}1");
        $khmerCompanyName = Setting::where('key', 'khmer_company_name')->value('value') ?? '';
        $sheet->setCellValue('A1', $khmerCompanyName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A2:{$highestCol}2");
        $companyName = Setting::where('key', 'company_name')->value('value') ?? '';
        $sheet->setCellValue('A2', $companyName);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A3:{$highestCol}3");
        $sheet->setCellValue('A3', 'Payment History');
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Sub Header Information
        $sheet->mergeCells('A5:B5');
        $sheet->setCellValue('A5', 'Contract No.');
        $sheet->mergeCells('C5:E5');
        $sheet->setCellValue('C5', $loanCode);
        
        $sheet->mergeCells('A6:B6');
        $sheet->setCellValue('A6', 'Client');
        $sheet->mergeCells('C6:E6');
        $sheet->setCellValue('C6', $customerName);
        
        $sheet->mergeCells('A7:B7');
        $sheet->setCellValue('A7', 'Currency');
        $sheet->mergeCells('C7:E7');
        $sheet->setCellValue('C7', $currency);
        
        $sheet->getStyle('A5:A7')->getFont()->setBold(true);
        
        // Headers
        $headers = [
            "No.", "Payment Date", "Principle", "Interest", "Total", "Actual Pay Date", 
            "Total Cash Paid", "Total Installment", "Penalty", "Prepayment", 
            "Outstanding", "Total Amount Due", "On-Time/Late"
        ];
        
        $row = 9;
        $sheet->getRowDimension($row)->setRowHeight(50);

        foreach ($headers as $index => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($colLetter . $row, $header);
            $sheet->getStyle($colLetter . $row)->getFont()->setBold(true);
            $sheet->getStyle($colLetter . $row)->getAlignment()
                  ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                  ->setVertical(Alignment::VERTICAL_CENTER)
                  ->setWrapText(true);
            $sheet->getStyle($colLetter . $row)->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()->setARGB('FFD3D3D3'); // Match other reports
                  
            // Column widths
            $width = 12;
            if (in_array($header, ["Payment Date", "Actual Pay Date"])) $width = 15;
            if (in_array($header, ["Principle", "Interest", "Total", "Total Cash Paid", "Total Installment", "Penalty", "Prepayment", "Outstanding", "Total Amount Due"])) $width = 16;
            if ($header === "No.") $width = 5;
            if ($header === "On-Time/Late") $width = 14;
            $sheet->getColumnDimension($colLetter)->setWidth($width);
        }

        $headerRange = "A{$row}:{$highestCol}{$row}";
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $row++;
        $startDataRow = $row;
        
        $totalPrincipal = 0;
        $totalInterest = 0;
        $totalTotal = 0;
        $totalCashPaid = 0;
        $totalInstallment = 0;
        $totalPenalty = 0;
        $totalPrepayment = 0;

        foreach ($historyData['payments'] as $payment) {
            $paymentDate = $payment['payment_date'] ? date('d/m/Y', strtotime($payment['payment_date'])) : '-';
            $actualPayDate = $payment['updated_at'] ? date('d/m/Y', strtotime($payment['updated_at'])) : '-';
            
            $col = 1;
            $sheet->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['payment_number'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $paymentDate);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['principal_amount']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['interest_amount']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['required_total']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $actualPayDate);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['total_paid']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['total_installment_value']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['penalty_amount']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['prepayment']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['outstanding_balance']);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payment['total_amount_due']);
            
            // On Time / Late
            $onTimeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
            // Wait, is it schedule_on_time_label or payment_on_time_label? In dart it calculates `onTimeLabel` based on payoff trigger or diff. The backend `loanToHistoryArray` returns `payment_on_time_label` for payments > 0, else `schedule_on_time_label`.
            $onTime = $payment['payment_on_time_label'] !== '0' ? $payment['payment_on_time_label'] : $payment['schedule_on_time_label'];
            
            $sheet->setCellValue($onTimeCol, $onTime);
            if (is_numeric($onTime) && (int)$onTime < 0) {
                $sheet->getStyle($onTimeCol)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED));
            } elseif (is_numeric($onTime) && (int)$onTime > 0) {
                $sheet->getStyle($onTimeCol)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKGREEN));
            }
            $col++;
            
            // Alignments
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Formats
            $sheet->getStyle("C{$row}:E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("G{$row}:L{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            
            // Accumulate Totals
            $totalPrincipal += $payment['principal_amount'];
            $totalInterest += $payment['interest_amount'];
            $totalTotal += $payment['required_total'];
            $totalCashPaid += $payment['total_paid'];
            $totalInstallment += $payment['total_installment_value'];
            $totalPenalty += $payment['penalty_amount'];
            $totalPrepayment += $payment['prepayment'];
            
            $row++;
        }

        // Totals Row
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", "Total");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $sheet->setCellValue("C{$row}", $totalPrincipal);
        $sheet->setCellValue("D{$row}", $totalInterest);
        $sheet->setCellValue("E{$row}", $totalTotal);
        $sheet->setCellValue("G{$row}", $totalCashPaid);
        $sheet->setCellValue("H{$row}", $totalInstallment);
        $sheet->setCellValue("I{$row}", $totalPenalty);
        $sheet->setCellValue("J{$row}", $totalPrepayment);
        
        $sheet->getStyle("C{$row}:L{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A{$row}:{$highestCol}{$row}")->getFont()->setBold(true);
        
        // Borders for Data
        $dataRange = "A{$startDataRow}:{$highestCol}{$row}";
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Output file
        $fileName = 'Payment_History_' . $loanCode . '.xlsx';
        
        ob_end_clean();
        ob_start();
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }
}
