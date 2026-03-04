<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\CustomerImport;
use Illuminate\Support\Facades\Log;

class CustomerImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // max 10MB
            'type' => 'required|string|in:borrowers,co-borrowers,guarantors,investors,savers'
        ]);

        $file = $request->file('file');
        $type = $request->input('type');

        try {
            $importer = new CustomerImport();
            $result = $importer->import($file->getPathname(), $type);

            if ($result['success']) {
                return response()->json([
                    'message' => 'Imported successfully.',
                    'count' => $result['count'],
                    'errors' => $result['errors']
                ]);
            } else {
                return response()->json([
                    'message' => 'Import failed.',
                    'error' => $result['message']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Customer Import Exception: " . $e->getMessage());
            return response()->json([
                'message' => 'An unexpected error occurred during import.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = [
                'First Name',
                'Last Name',
                'Gender',
                'Phone',
                'ID Type',
                'ID Number',
                'DOB (YYYY-MM-DD)',
                'Village',
                'Commune',
                'District',
                'Province'
            ];

            // Set headers
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $col++;
            }

            // Add sample row
            $sheet->setCellValue('A2', 'Sok');
            $sheet->setCellValue('B2', 'Chea');
            $sheet->setCellValue('C2', 'Male');
            $sheet->setCellValue('D2', '012345678');
            $sheet->setCellValue('E2', 'NID');
            $sheet->setCellValue('F2', '');
            $sheet->setCellValue('G2', '1990-12-30');
            $sheet->setCellValue('H2', '');
            $sheet->setCellValue('I2', '');
            $sheet->setCellValue('J2', '');
            $sheet->setCellValue('K2', '');

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

            $fileName = 'Customer_Import_Template.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), 'tpl');
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error("Template Download Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to generate template'], 500);
        }
    }
}
