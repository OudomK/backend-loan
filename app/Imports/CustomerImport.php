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

            // Extract headers and map indices
            $headers = [];
            if (count($rows) > 0) {
                $headers = array_map(function($h) { return strtolower(trim((string)$h)); }, $rows[0]);
                array_shift($rows); // Remove header row
            }

            // Fallback indices if headers are not perfectly matched
            $colMap = [
                'first_name' => 0, 'last_name' => 1, 'latin_name' => 2, 'gender' => 3, 'dob' => 4,
                'phone' => 5, 'id_type' => 6, 'id_number' => 7, 'id_expiry' => 8, 'occupation' => 9,
                'marital_status' => 10, 'village' => 11, 'commune' => 12, 'district' => 13, 'province' => 14,
                'id_issued_date' => -1, // Optional
            ];

            // Dynamically map if headers exist
            if (!empty($headers)) {
                $findCol = function($names) use ($headers) {
                    foreach ((array)$names as $name) {
                        $search = strtolower($name);
                        foreach ($headers as $idx => $header) {
                            if (str_contains($header, $search)) {
                                return $idx;
                            }
                        }
                    }
                    return -1;
                };

                $colMap['first_name'] = $findCol('first name');
                $colMap['last_name'] = $findCol('last name');
                $colMap['latin_name'] = $findCol(['latin name', 'latang']);
                $colMap['gender'] = $findCol('gender');
                $colMap['dob'] = $findCol(['dob', 'date of birth']);
                $colMap['phone'] = $findCol(['phone', 'phone number']);
                $colMap['id_type'] = $findCol('id type');
                $colMap['id_number'] = $findCol(['id number', 'identity id']);
                $colMap['id_expiry'] = $findCol(['id expiry', 'identity expiry']);
                $colMap['occupation'] = $findCol('occupation');
                $colMap['marital_status'] = $findCol(['marital status', 'marital_status']);
                $colMap['village'] = $findCol('village');
                $colMap['commune'] = $findCol('commune');
                $colMap['district'] = $findCol('district');
                $colMap['province'] = $findCol('province');
                $colMap['id_issued_date'] = $findCol(['issue', 'issued', 'dated']);
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

                $getVal = function($key) use ($row, $colMap) {
                    $idx = $colMap[$key] ?? -1;
                    $val = $idx >= 0 ? trim((string)($row[$idx] ?? '')) : '';
                    return $val === '' ? null : $val;
                };

                $firstName = $getVal('first_name');
                $lastName = $getVal('last_name');
                $latinName = $getVal('latin_name');

                if (empty($firstName) || empty($lastName)) {
                    $errors[] = "Row " . ($index + 2) . ": First Name and Last Name are required.";
                    continue;
                }

                $currentSequence++;
                $separator = ($prefix === 'INV') ? '' : '-';
                $customerCode = $prefix . $separator . str_pad($currentSequence, 3, '0', STR_PAD_LEFT);

                $data = [
                    'row_no' => $currentSequence,
                    'customer_code' => $customerCode,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'latin_name' => $latinName,
                    'gender' => match(strtoupper($getVal('gender'))) {
                        'F', 'FEMALE' => 'Female',
                        'M', 'MALE' => 'Male',
                        default => 'Other'
                    },
                    'dob' => $this->parseDate($getVal('dob')),
                    'phone' => $getVal('phone'),
                    'id_type' => $getVal('id_type') ?: 'National ID',
                    'id_number' => $getVal('id_number'),
                    'id_expiry' => $this->parseDate($getVal('id_expiry')),
                    'id_issue_date' => $this->parseDate($getVal('id_issued_date')),
                    'occupation' => $getVal('occupation'),
                    'marital_status' => $getVal('marital_status'),
                    'village' => $getVal('village'),
                    'commune' => $getVal('commune'),
                    'district' => $getVal('district'),
                    'province' => $getVal('province'),
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
