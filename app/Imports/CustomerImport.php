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
     * 2: Latin Name
     * 3: Gender
     * 4: DOB (DD/MM/YYYY)
     * 5: Phone
     * 6: ID Type (e.g., National ID)
     * 7: ID Number
     * 8: ID Expiry (DD/MM/YYYY)
     * 9: Occupation
     * 10: Marital Status
     * 11: Village
     * 12: Commune
     * 13: District
     * 14: Province
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
                $latinName = trim($row[2] ?? '');

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
                    'latin_name' => $latinName,
                    'gender' => trim($row[3] ?? 'Male'),
                    'dob' => $this->parseDate($row[4] ?? null),
                    'phone' => trim($row[5] ?? ''),
                    'id_type' => trim($row[6] ?? 'National ID'),
                    'id_number' => trim($row[7] ?? ''),
                    'id_expiry' => $this->parseDate($row[8] ?? null),
                    'occupation' => trim($row[9] ?? ''),
                    'marital_status' => trim($row[10] ?? ''),
                    'village' => trim($row[11] ?? ''),
                    'commune' => trim($row[12] ?? ''),
                    'district' => trim($row[13] ?? ''),
                    'province' => trim($row[14] ?? ''),
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

        // Parse the actual numeric suffix from the highest customer_code
        // to avoid duplicate code conflicts (id ≠ code sequence after deletions/manual edits)
        $separator = ($prefix === 'INV') ? '' : '-';
        $pattern = $prefix . $separator;

        $lastRecord = $modelClass::withTrashed()
            ->where('customer_code', 'like', $pattern . '%')
            ->orderByRaw("CAST(SUBSTRING(customer_code, ?) AS UNSIGNED) DESC", [strlen($pattern) + 1])
            ->first();

        if (!$lastRecord) {
            return 0;
        }

        // Extract numeric part after the prefix+separator
        $numericPart = substr($lastRecord->customer_code, strlen($pattern));
        return (int) $numericPart;
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
