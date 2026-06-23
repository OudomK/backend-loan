<?php

namespace App\Exports\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Setting;

class LoanOutstandingParExcelExport
{
    public function download(array $data, Request $request, ?string $reportDateStr)
    {
        $spreadsheet = new Spreadsheet();

        $excelFont = Setting::where('key', 'excel_export_font')->value('value') ?? 'Khmer OS Siemreap';
        $spreadsheet->getDefaultStyle()->getFont()->setName($excelFont);
        $spreadsheet->getDefaultStyle()->getFont()->setSize(8);

        $sheet = $spreadsheet->getActiveSheet();

        $refDate = $reportDateStr ? Carbon::parse($reportDateStr) : Carbon::today();
        $dateStr = $refDate->format('d/m/Y');

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

        // Fetch dynamic company names
        $khmerCompanyName = Setting::where('key', 'company_name_kh')->value('value') ?? 'ប្រាក់.រហ័ស ហ្វាយនែន ម.ក';
        $englishCompanyName = Setting::where('key', 'company_name_en')->value('value') ?? 'Quick Fund Finance Plc.';

        // Title and Header Information
        $sheet->getRowDimension(1)->setRowHeight(45); // Space for taller logo
        
        // Auto-sizing or setting column widths to make it beautiful
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(18);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(15);
        $sheet->getColumnDimension('L')->setWidth(15);
        $sheet->getColumnDimension('M')->setWidth(15);
        $sheet->getColumnDimension('N')->setWidth(15);

        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', $khmerCompanyName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:N2');
        $sheet->setCellValue('A2', $englishCompanyName);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:N3');
        $sheet->setCellValue('A3', 'Loan Outstanding and PAR Report');
        $sheet->getStyle('A3')->getFont()->setSize(11);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A4:N4');
        $sheet->setCellValue('A4', 'As At ' . $dateStr . ', Exchange Rate 4000');
        $sheet->getStyle('A4')->getFont()->setSize(10);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Group Headers (Row 6)
        $sheet->mergeCells('A6:A7'); // No.
        $sheet->setCellValue('A6', 'No');

        $sheet->mergeCells('B6:D6'); // Loan Outstanding
        $sheet->setCellValue('B6', 'Loan Outstanding');

        $sheet->mergeCells('E6:G6'); // PAR $
        $sheet->setCellValue('E6', 'PAR $');

        $sheet->setCellValue('H6', 'TOTAL');
        $sheet->setCellValue('I6', 'PAR NPL');
        $sheet->setCellValue('J6', 'PAR %');

        $sheet->mergeCells('K6:N6'); // Loan Count
        $sheet->setCellValue('K6', 'Loan Count');

        // Sub Headers (Row 7)
        $subHeaders = [
            'B' => 'USD',
            'C' => 'KHR (in$)',
            'D' => 'Total USD',
            'E' => 'USD',
            'F' => 'KHR (in$)',
            'G' => 'Total USD',
            'H' => 'PAR%',
            'I' => 'Total USD',
            'J' => 'NPL',
            'K' => '#Active Loan',
            'L' => '#PAR1',
            'M' => '#PAR<=30',
            'N' => '#PAR>30'
        ];

        foreach ($subHeaders as $col => $header) {
            $sheet->setCellValue($col . '7', $header);
        }

        $sheet->getRowDimension(6)->setRowHeight(25);
        $sheet->getRowDimension(7)->setRowHeight(25);

        // Header Styling
        $headerRange = 'A6:N7';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
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
            'usd_os' => 0,
            'khr_os_usd' => 0,
            'total_os' => 0,
            'par_usd' => 0,
            'par_khr_usd' => 0,
            'par_total' => 0,
            'npl_amount' => 0,
            'active_loan' => 0,
            'par1' => 0,
            'par30' => 0,
            'par30plus' => 0
        ];

        $no = 1;
        $exchangeRate = 4000;

        foreach ($data as $item) {
            $sheet->getRowDimension($row)->setRowHeight(21);
            $sheet->setCellValue('A' . $row, $no++);

            $usdOs = (float) ($item['usd_loan_os'] ?? 0);
            $khrOsUsd = (float) ($item['khr_loan_os'] ?? 0) / $exchangeRate;
            $totalOs = (float) ($item['total_loan_os'] ?? 0);

            $parUsd = (float) ($item['par_usd_amount'] ?? 0);
            $parKhrUsd = (float) ($item['par_khr_amount'] ?? 0) / $exchangeRate;
            $parTotal = (float) ($item['par_total_amount'] ?? 0);

            $parPercent = (float) ($item['par_percent'] ?? 0);
            $nplAmount = (float) ($item['npl_amount'] ?? 0);
            $nplPercent = (float) ($item['npl_percent'] ?? 0);

            $activeCount = (int) ($item['active_loan_count'] ?? 0);
            $par1Count = (int) ($item['par1_count'] ?? 0);
            $par30Count = (int) ($item['par_lte_30_count'] ?? 0);
            $par30PlusCount = (int) ($item['par_gt_30_count'] ?? 0);

            // Accumulate totals
            $totals['usd_os'] += $usdOs;
            $totals['khr_os_usd'] += $khrOsUsd;
            $totals['total_os'] += $totalOs;
            $totals['par_usd'] += $parUsd;
            $totals['par_khr_usd'] += $parKhrUsd;
            $totals['par_total'] += $parTotal;
            $totals['npl_amount'] += $nplAmount;
            $totals['active_loan'] += $activeCount;
            $totals['par1'] += $par1Count;
            $totals['par30'] += $par30Count;
            $totals['par30plus'] += $par30PlusCount;

            // Set cell values
            $sheet->setCellValueExplicit('B' . $row, $usdOs, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('C' . $row, $khrOsUsd, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('D' . $row, $totalOs, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            $sheet->setCellValueExplicit('E' . $row, $parUsd, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('F' . $row, $parKhrUsd, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('G' . $row, $parTotal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            $sheet->setCellValue('H' . $row, number_format($parPercent, 2) . '%');
            $sheet->setCellValueExplicit('I' . $row, $nplAmount, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValue('J' . $row, number_format($nplPercent, 2) . '%');

            // Format 0 values as "-" for counts
            $sheet->setCellValue('K' . $row, $activeCount > 0 ? $activeCount : '-');
            $sheet->getStyle('K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->setCellValue('L' . $row, $par1Count > 0 ? $par1Count : '-');
            $sheet->getStyle('L' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->setCellValue('M' . $row, $par30Count > 0 ? $par30Count : '-');
            $sheet->getStyle('M' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->setCellValue('N' . $row, $par30PlusCount > 0 ? $par30PlusCount : '-');
            $sheet->getStyle('N' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $row++;
        }

        // Apply number format to currency columns
        $sheet->getStyle('B8:G' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('I8:I' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');

        // Summary Totals Row
        $sheet->getRowDimension($row)->setRowHeight(25);
        $sheet->setCellValue('A' . $row, 'សរុប');
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->setCellValue('B' . $row, $totals['usd_os']);
        $sheet->setCellValue('C' . $row, $totals['khr_os_usd']);
        $sheet->setCellValue('D' . $row, $totals['total_os']);

        $sheet->setCellValue('E' . $row, $totals['par_usd']);
        $sheet->setCellValue('F' . $row, $totals['par_khr_usd']);
        $sheet->setCellValue('G' . $row, $totals['par_total']);

        $totalParPercent = $totals['total_os'] > 0 ? ($totals['par_total'] / $totals['total_os']) * 100 : 0;
        $sheet->setCellValue('H' . $row, number_format($totalParPercent, 2) . '%');

        $sheet->setCellValue('I' . $row, $totals['npl_amount']);

        $totalNplPercent = $totals['total_os'] > 0 ? ($totals['npl_amount'] / $totals['total_os']) * 100 : 0;
        $sheet->setCellValue('J' . $row, number_format($totalNplPercent, 2) . '%');

        $sheet->setCellValue('K' . $row, $totals['active_loan']);
        $sheet->setCellValue('L' . $row, $totals['par1'] > 0 ? $totals['par1'] : '-');
        $sheet->setCellValue('M' . $row, $totals['par30'] > 0 ? $totals['par30'] : '-');
        $sheet->setCellValue('N' . $row, $totals['par30plus'] > 0 ? $totals['par30plus'] : '-');

        // Format Total Row
        $totalRange = 'A' . $row . ':N' . $row;
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                'top' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);
        $sheet->getStyle('B' . $row . ':G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        $sheet->getStyle('K' . $row . ':N' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($totalRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Apply borders and alignment to all data cells
        if ($row > 8) {
            $dataRange = 'A8:N' . ($row - 1);
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        // Output to browser
        $writer = new Xlsx($spreadsheet);
        $fileName = "Loan_Outstanding_PAR_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
