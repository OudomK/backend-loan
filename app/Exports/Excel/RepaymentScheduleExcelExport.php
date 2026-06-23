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

class RepaymentScheduleExcelExport
{
    public function download(array $data, Request $request)
    {
        $spreadsheet = new Spreadsheet();

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont);
        $spreadsheet->getDefaultStyle()->getFont()->setSize(8);

        $sheet = $spreadsheet->getActiveSheet();

        // Hide default gridlines
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

        $highestCol = 'N'; // Similar visible span as other reports

        $sheet->mergeCells("A1:{$highestCol}1");
        $sheet->setCellValue('A1', 'ប្រាក់.រហ័ស ហ្វាយនែន ម.ក');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A2:{$highestCol}2");
        $sheet->setCellValue('A2', 'Quick Fund Finance Plc.');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("A3:{$highestCol}3");
        $sheet->setCellValue('A3', 'Schedule Repay');
        $sheet->getStyle('A3')->getFont()->setSize(11);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $fromDate = $request->query('start_date');
        $toDate = $request->query('end_date');
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

        // Headers
        $headers = [
            "No.",
            "Payment Date",
            "Loan Code",
            "Client Name",
            "Phone",
            "Village",
            "Commune",
            "District",
            "Province",
            "Collateral",
            "Currency",
            "Installment",
            "Loan Amount",
            "OutStanding",
            "Principal",
            "Interest",
            "Total",
            "Total Paid",
            "Remaining",
            "Status",
            "Credit Officer"
        ];

        // Format Headers
        $row = 7;
        $sheet->getRowDimension($row)->setRowHeight(50);

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
        $totalsUSD = [
            'loan_amount' => 0,
            'outstanding_balance' => 0,
            'principal_amount' => 0,
            'interest_amount' => 0,
            'total_due' => 0,
            'total_paid' => 0,
            'remaining' => 0,
        ];
        $totalsKHR = [
            'loan_amount' => 0,
            'outstanding_balance' => 0,
            'principal_amount' => 0,
            'interest_amount' => 0,
            'total_due' => 0,
            'total_paid' => 0,
            'remaining' => 0,
        ];

        $no = 1;
        foreach ($data as $item) {
            $sheet->getRowDimension($row)->setRowHeight(21);
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $no++);

            // Payment Date
            $paymentDate = $item['payment_date'] ?? '-';
            if ($paymentDate !== '-' && !empty($paymentDate)) {
                $paymentDate = \Carbon\Carbon::parse($paymentDate)->format('d/m/Y');
            }
            $sheet->setCellValue($col++ . $row, $paymentDate);

            // Loan Code
            $sheet->setCellValue($col++ . $row, $item['loan_code'] ?? '-');

            // Client Name
            $clientName = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
            $sheet->setCellValue($col++ . $row, $clientName ?: '-');

            // Demographics
            $phone = trim($item['phone'] ?? '-');
            if ($phone !== '-' && !empty($phone)) {
                $cleanPhone = str_replace([' ', '-'], '', $phone);
                if (strlen($cleanPhone) >= 9) {
                    $phone = substr($cleanPhone, 0, 3) . ' ' . substr($cleanPhone, 3, 3) . ' ' . substr($cleanPhone, 6);
                }
            }
            $sheet->setCellValueExplicit($col++ . $row, $phone, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue($col++ . $row, $item['village'] ?? '-');
            $sheet->setCellValue($col++ . $row, $item['commune'] ?? '-');
            $sheet->setCellValue($col++ . $row, $item['district'] ?? '-');
            $sheet->setCellValue($col++ . $row, $item['province'] ?? '-');

            // Collateral
            $sheet->setCellValue($col++ . $row, $item['collaterals'] ?? '-');

            // Currency
            $rawCurrency = $item['currency'] ?? 'USD';
            $currency = explode(' ', $rawCurrency)[0];
            $sheet->setCellValue($col++ . $row, $currency);

            // Installment
            $sheet->setCellValue($col++ . $row, $item['installment_display'] ?? '-');

            // Financial Amounts
            $amounts = [
                'loan_amount' => $item['loan_amount'] ?? 0,
                'outstanding_balance' => $item['outstanding_balance'] ?? 0,
                'principal_amount' => $item['principal_amount'] ?? 0,
                'interest_amount' => $item['interest_amount'] ?? 0,
                'total_due' => $item['total_due'] ?? 0,
                'total_paid' => $item['total_paid'] ?? 0,
                'remaining' => $item['remaining'] ?? 0,
            ];

            foreach ($amounts as $key => $amount) {
                $sheet->setCellValueExplicit($col++ . $row, $amount, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $val = (float) str_replace(',', '', $amount);
                if (strtoupper($currency) === 'KHR') {
                    $totalsKHR[$key] += $val;
                } else {
                    $totalsUSD[$key] += $val;
                }
            }

            // Status
            $status = $item['payment_status'] ?? '-';
            if (is_string($status)) {
                $status = ucfirst($status);
            }
            $sheet->setCellValue($col++ . $row, $status);

            // Officer
            $sheet->setCellValue($col++ . $row, $item['officer_name'] ?? '-');

            $row++;
        }

        // Summary Totals Row Combined
        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->mergeCells('A' . $row . ':M' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A' . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $keys = [
            'N' => 'loan_amount',
            'O' => 'outstanding_balance',
            'P' => 'principal_amount',
            'Q' => 'interest_amount',
            'R' => 'total_due',
            'S' => 'total_paid',
            'T' => 'remaining'
        ];

        foreach ($keys as $colLetter => $key) {
            $usd = 'USD ' . number_format($totalsUSD[$key], 2);
            $khr = 'KHR ' . number_format($totalsKHR[$key], 0);
            
            // Only show currency if it has a value, or show both if both are 0
            $combined = $usd . "\n" . $khr;
            
            $sheet->setCellValue($colLetter . $row, $combined);
            $sheet->getStyle($colLetter . $row)->getAlignment()->setWrapText(true);
            $sheet->getStyle($colLetter . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($colLetter . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Format Combined Row
        $totalRange = 'A' . $row . ':' . $sheet->getHighestColumn() . $row;
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true, 'name' => $excelFont, 'size' => 8],
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
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
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
        $fileName = "Schedule_Repay_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
