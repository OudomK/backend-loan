<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Borrower;
use App\Models\CoBorrower;
use App\Models\Guarantor;
use App\Models\Investor;
use App\Models\Saver;

class CustomerImport
{
    /**
     * Import customers from an Excel or CSV file.
     * 
     * Expected Columns (0-indexed):
     * 0: First Name*
     * 1: Last Name*
     * 2: Gender
     * 3: Phone
     * 4: ID Type (e.g., NID)
     * 5: ID Number
     * 6: DOB (YYYY-MM-DD or via Excel format)
     * 7: Village
     * 8: Commune
     * 9: District
     * 10: Province
     */
    public function import($filePath, $type)
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Remove header row if exists
            if (count($rows) > 0 && isset($rows[0][0]) && strtolower(trim($rows[0][0])) === 'first name') {
                array_shift($rows);
            }

            $successCount = 0;
            $errors = [];

            DB::beginTransaction();

            $prefix = $this->getPrefix($type);
            $currentSequence = $this->getLastSequence($type, $prefix);

            foreach ($rows as $index => $row) {
                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $firstName = trim($row[0] ?? '');
                $lastName = trim($row[1] ?? '');

                if (empty($firstName) || empty($lastName)) {
                    $errors[] = "Row " . ($index + 2) . ": First Name and Last Name are required.";
                    continue;
                }

                $currentSequence++;
                $customerCode = $prefix . '-' . str_pad($currentSequence, 3, '0', STR_PAD_LEFT);

                $data = [
                    'customer_code' => $customerCode,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'gender' => trim($row[2] ?? 'Male'),
                    'phone' => trim($row[3] ?? ''),
                    'id_type' => trim($row[4] ?? 'NID'),
                    'id_number' => trim($row[5] ?? ''),
                    'dob' => $this->parseDate($row[6] ?? null),
                    'village' => trim($row[7] ?? ''),
                    'commune' => trim($row[8] ?? ''),
                    'district' => trim($row[9] ?? ''),
                    'province' => trim($row[10] ?? ''),
                    'status' => 'Active',
                ];

                $model = $this->getModel($type);
                if ($model) {
                    $model::create($data);
                    $successCount++;
                } else {
                    throw new \Exception("Invalid customer type: $type");
                }
            }

            DB::commit();

            return [
                'success' => true,
                'count' => $successCount,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Excel Import Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
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

    private function getPrefix($type)
    {
        return match ($type) {
            'borrowers' => 'BOR',
            'co-borrowers' => 'COB',
            'guarantors' => 'GUA',
            'investors' => 'INV',
            'savers' => 'SAV',
            default => 'CUS'
        };
    }

    private function getLastSequence($type, $prefix)
    {
        $modelClass = $this->getModel($type);
        if (!$modelClass)
            return 0;

        $lastRecord = $modelClass::orderBy('id', 'desc')->first();
        if (!$lastRecord || empty($lastRecord->customer_code)) {
            return 0; // Starts at 0, first will be 1
        }

        $lastCode = $lastRecord->customer_code;
        $parts = explode('-', $lastCode);

        if (count($parts) > 1 && is_numeric($parts[1])) {
            return intval($parts[1]);
        }

        return 0;
    }


    private function parseDate($value)
    {
        if (empty($value))
            return null;

        // Ensure it's not simply an empty string or spaces
        $val = trim($value);
        if ($val === '')
            return null;

        // If it's a numeric value from Excel (approx serial date)
        if (is_numeric($val)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)->format('Y-m-d');
            } catch (\Exception $e) {
                // If it fails, fallback
            }
        }

        // Try standard conversion
        try {
            return \Carbon\Carbon::parse($val)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
