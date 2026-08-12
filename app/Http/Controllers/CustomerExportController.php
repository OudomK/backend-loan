<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\Borrower;
use App\Models\CoBorrower;
use App\Models\Guarantor;
use App\Models\Investor;
use App\Models\Saver;

class CustomerExportController extends Controller
{
    public function export(Request $request)
    {
        $type = $request->query('type', 'borrowers');

        try {
            $modelClass = $this->getModel($type);
            if (!$modelClass) {
                return response()->json(['error' => 'Invalid customer type'], 400);
            }

            // You could apply filtering from the request here if needed
            $customers = $modelClass::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set Titles
            $sheet->setTitle(ucfirst($type));

            $headers = [
                'ID',
                'Customer Code',
                'First Name',
                'Last Name',
                'Gender',
                'Age',
                'DOB',
                'Phone',
                'ID Type',
                'ID Number',
                'ID Expiry',
                'Occupation',
                'Marital Status',
                'Village',
                'Commune',
                'District',
                'Province',
                'Status'
            ];

            // Set Header Row
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $col++;
            }

            // Fill Data
            $rowNum = 2;
            foreach ($customers as $customer) {
                $sheet->setCellValue('A' . $rowNum, $customer->id);
                $sheet->setCellValue('B' . $rowNum, $customer->customer_code);
                $sheet->setCellValue('C' . $rowNum, $customer->first_name);
                $sheet->setCellValue('D' . $rowNum, $customer->last_name);
                $sheet->setCellValue('E' . $rowNum, $customer->formatted_gender);
                $sheet->setCellValue('F' . $rowNum, $customer->age);
                $sheet->setCellValue('G' . $rowNum, $customer->dob);
                $sheet->setCellValue('H' . $rowNum, $customer->phone);
                $sheet->setCellValue('I' . $rowNum, $customer->id_type);
                $sheet->setCellValueExplicit('J' . $rowNum, $customer->id_number, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('K' . $rowNum, $customer->id_expiry);
                $sheet->setCellValue('L' . $rowNum, $customer->occupation);
                $sheet->setCellValue('M' . $rowNum, $customer->marital_status);
                $sheet->setCellValue('N' . $rowNum, $customer->village);
                $sheet->setCellValue('O' . $rowNum, $customer->commune);
                $sheet->setCellValue('P' . $rowNum, $customer->district);
                $sheet->setCellValue('Q' . $rowNum, $customer->province);
                $sheet->setCellValue('R' . $rowNum, $customer->status);
                $rowNum++;
            }

            $writer = new Xlsx($spreadsheet);

            $fileName = 'Export_' . ucfirst($type) . '_' . date('Ymd_His') . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), 'exp');
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error("Customer Export Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to export customers: ' . $e->getMessage()], 500);
        }
    }

    private function getModel($type)
    {
        return match ($type) {
            'borrowers' => Borrower::class,
            'co-borrowers' => CoBorrower::class,
            'guarantors' => Guarantor::class,
            'investors' => Investor::class,
            'savers' => Saver::class,
            default => null,
        };
    }
}
