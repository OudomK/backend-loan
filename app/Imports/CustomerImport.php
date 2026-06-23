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
     * 3: DOB (DD/MM/YYYY)
     * 4: Phone
     * 5: ID Type (e.g., National ID)
     * 6: ID Number
     * 7: ID Expiry (DD/MM/YYYY)
     * 8: Occupation
     * 9: Marital Status
     * 10: Village
     * 11: Commune
     * 12: District
     * 13: Province
     */
    public function import(string $filePath, string $type)
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
                $separator = ($prefix === 'INV') ? '' : '-';
                $customerCode = $prefix . $separator . str_pad($currentSequence, 3, '0', STR_PAD_LEFT);

                $data = [
                    'customer_code' => $customerCode,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'gender' => trim($row[2] ?? 'Male'),
                    'dob' => $this->parseDate($row[3] ?? null),
                    'phone' => trim($row[4] ?? ''),
                    'id_type' => trim($row[5] ?? 'National ID'),
                    'id_number' => trim($row[6] ?? ''),
                    'id_expiry' => $this->parseDate($row[7] ?? null),
                    'occupation' => trim($row[8] ?? ''),
                    'marital_status' => trim($row[9] ?? ''),
                    'village' => trim($row[10] ?? ''),
                    'commune' => trim($row[11] ?? ''),
                    'district' => trim($row[12] ?? ''),
                    'province' => trim($row[13] ?? ''),
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

    private function getModel(string $type)
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

    private function getPrefix(string $type)
    {
        return match ($type) {
            'borrowers' => 'QF',
            'co-borrowers' => 'CB',
            'guarantors' => 'GU',
            'investors' => 'INV',
            'savers' => 'SAV',
            default => 'CUS'
        };
    }

    private function getLastSequence(string $type, string $prefix)
    {
        $modelClass = $this->getModel($type);
        if (!$modelClass)
            return 0;

        $lastRecord = $modelClass::withTrashed()->orderBy('id', 'desc')->first();
        if (!$lastRecord) {
            return 0;
        }

        // Just use the latest ID as the sequence basis to match BorrowerController logic
        return $lastRecord->id;
    }


    private function parseDate(?string $value)
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
        // Try Carbon parse which usually handles many formats including DD/MM/YYYY
        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $val)->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return \Carbon\Carbon::parse($val)->format('Y-m-d');
            } catch (\Exception $e2) {
                return null;
            }
        }
    }
}
