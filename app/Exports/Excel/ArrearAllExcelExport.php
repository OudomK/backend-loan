<?php

namespace App\Exports\Excel;

use App\Models\Setting;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ArrearAllExcelExport
{
    public function download(array $data, Request $request, ?string $reportDate, ?string $currency, ?string $officerName)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Hide default gridlines
        $sheet->setShowGridlines(false);

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont)->setSize(8);

        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? 'ប្រាក់ រហ័ស ហ្វាយនែន ម.ក';
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? 'Quick Fund Finance Plc.';
        $reportTitle = 'Loan In Arrears Report';

        $normalizedCurrency = strtoupper($currency);

        $usdData = array_filter($data, function ($item) {
            return str_contains(strtoupper($item['currency'] ?? ''), 'USD');
        });
        $khrData = array_filter($data, function ($item) {
            return str_contains(strtoupper($item['currency'] ?? ''), 'KHR');
        });

        // Styles
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D3D3D3'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        // 1. Title area & Logo
        $drawing = new Drawing;
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

        $highestCol = 'N'; // Limit title merge span to keep it visibly centered near the left

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

        $asAt = $reportDate ? \Carbon\Carbon::parse($reportDate)->format('d/m/Y') : '';
        $filterInfo = "As At: $asAt , Currency: ".($normalizedCurrency == 'ALL' ? 'ALL' : $normalizedCurrency)." , CO: $officerName";
        $sheet->mergeCells("A4:{$highestCol}4");
        $sheet->setCellValue('A4', $filterInfo);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4')->getFont()->setSize(10);

        $headers = [
            'Arrear Date', 'Loan No.', 'Name', 'Co-Borrower', 'Guarantor',
            'Gender', 'Phone', 'CB Phone', 'GU Phone',
            'C.O', 'Village', 'Commune', 'Last Payment Date', 'Aging',
            'Types of Collateral', 'Number', 'Date Disb.',
            'Disb. Amount', 'OutStanding', 'Arrear Amount', 'Arrear Interest', 'Arrear Fee',
            'Penalty Due', 'Penalty Paid', 'Status',
        ];

        $keys = [
            'arrear_date', 'loan_no', 'name', 'coborrower', 'guarantor',
            'gender', 'phone', 'coborrower_phone', 'guarantor_phone',
            'co', 'village', 'commune', 'last_payment_date', 'aging',
            'types_of_collateral', 'number', 'date_disbursement',
            'disb_amount', 'outstanding', 'arrear_amount', 'arrear_interest', 'arrear_fee',
            'penalty_due', 'penalty_paid', 'status',
        ];

        $numericKeys = ['disb_amount', 'outstanding', 'arrear_amount', 'arrear_interest', 'arrear_fee', 'penalty_due', 'penalty_paid'];

        $row = 7;

        $sections = [
            'USD' => $usdData,
            'KHR' => $khrData,
        ];

        foreach ($sections as $curr => $sectionData) {
            if ($normalizedCurrency === 'ALL' || $normalizedCurrency === $curr) {
                // Section Header
                $sheet->setCellValue('A'.$row, $curr);
                $sheet->setCellValue('B'.$row, count($sectionData));
                $sheet->getStyle('A'.$row.':B'.$row)->applyFromArray($headerStyle);
                $row++;

                // Table Headers
                $colIndex = 1;
                foreach ($headers as $header) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($columnLetter.$row, $header);
                    $colIndex++;
                }
                $sheet->getStyle('A'.$row.':Y'.$row)->applyFromArray($headerStyle);
                $sheet->getRowDimension($row)->setRowHeight(50);
                $row++;

                // Data Rows
                $totals = array_fill_keys($numericKeys, 0);

                foreach ($sectionData as $item) {
                    $colIndex = 1;
                    foreach ($keys as $key) {
                        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                        $value = $item[$key] ?? '-';

                        if (in_array($key, ['arrear_date', 'last_payment_date', 'date_disbursement'])) {
                            if ($value !== '-' && $value) {
                                $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                            }
                        }

                        if (in_array($key, ['phone', 'coborrower_phone', 'guarantor_phone'])) {
                            if ($value !== '-' && ! empty($value)) {
                                $cleanPhone = str_replace([' ', '-'], '', $value);
                                if (strlen($cleanPhone) >= 9) {
                                    $value = substr($cleanPhone, 0, 3).' '.substr($cleanPhone, 3, 3).' '.substr($cleanPhone, 6);
                                }
                            }
                        }

                        if (in_array($key, $numericKeys)) {
                            $floatVal = (float) $value;
                            $totals[$key] += $floatVal;
                            $sheet->setCellValue($columnLetter.$row, $floatVal);
                            $sheet->getStyle($columnLetter.$row)->getNumberFormat()->setFormatCode('#,##0.00');
                        } elseif (in_array($key, ['aging', 'number']) && is_numeric($value)) {
                            $sheet->setCellValueExplicit($columnLetter.$row, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        } else {
                            if ($key === 'status') {
                                $value = strtoupper($value);
                            }
                            // Store phone numbers and other text with standard setCellValue to avoid strict TYPE_STRING warning if formatted with spaces
                            if (in_array($key, ['phone', 'coborrower_phone', 'guarantor_phone']) || ! is_numeric($value)) {
                                $sheet->setCellValueExplicit($columnLetter.$row, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            } else {
                                $sheet->setCellValue($columnLetter.$row, $value);
                            }
                        }

                        if (in_array($key, ['gender', 'aging', 'types_of_collateral', 'number', 'status'])) {
                            $sheet->getStyle($columnLetter.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }

                        $colIndex++;
                    }
                    $sheet->getStyle('A'.$row.':Y'.$row)->applyFromArray($dataStyle);
                    $row++;
                }

                // Totals Row
                $sheet->mergeCells('A'.$row.':Q'.$row);
                $sheet->setCellValue('A'.$row, 'Total');
                $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $colIndex = 1;
                foreach ($keys as $key) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    if (in_array($key, $numericKeys)) {
                        $sheet->setCellValue($columnLetter.$row, $totals[$key]);
                        $sheet->getStyle($columnLetter.$row)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    $colIndex++;
                }

                // Only style A to X (Y is status, no total needed)
                $sheet->getStyle('A'.$row.':X'.$row)->applyFromArray($headerStyle);
                $row += 2; // Add spacer between tables
            }
        }

        // Auto-size columns
        for ($colIndex = 1; $colIndex <= 25; $colIndex++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        // Output to browser
        $writer = new Xlsx($spreadsheet);
        $fileName = 'Loan_in_Arrears_(All)_'.date('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
