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
    public function import(string $filePath, string $type, bool $force = false)
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Extract headers and map indices
            $headers = [];
            if (count($rows) > 0) {
                $headers = array_map(function ($h) {
                    return strtolower(trim((string) $h)); }, $rows[0]);
                array_shift($rows); // Remove header row
            }

            // Fallback indices if headers are not perfectly matched
            $colMap = [
                'first_name' => 0,
                'last_name' => 1,
                'latin_name' => 2,
                'gender' => 3,
                'dob' => 4,
                'phone' => 5,
                'id_type' => 6,
                'id_number' => 7,
                'id_expiry' => 8,
                'occupation' => 9,
                'marital_status' => 10,
                'village' => 11,
                'commune' => 12,
                'district' => 13,
                'province' => 14,
                'id_issued_date' => -1, // Optional
            ];

            // Dynamically map if headers exist
            $hasHeaders = false;
            if (!empty($headers)) {
                $findCol = function ($names) use ($headers) {
                    foreach ((array) $names as $name) {
                        $search = strtolower($name);
                        foreach ($headers as $idx => $header) {
                            if (str_contains($header, $search)) {
                                return $idx;
                            }
                        }
                    }
                    return -1;
                };

                $tempMap = [
                    'first_name' => $findCol(['first name', 'firstname', 'first_name', 'name', 'នាមខ្លួន']),
                    'last_name' => $findCol(['last name', 'lastname', 'last_name', 'surname', 'នាមត្រកូល']),
                    'latin_name' => $findCol(['latin name', 'latang', 'latin_name', 'english name']),
                    'gender' => $findCol(['gender', 'sex', 'ភេទ']),
                    'dob' => $findCol(['dob', 'date of birth', 'birth date', 'birthdate', 'ថ្ងៃខែឆ្នាំកំណើត']),
                    'phone' => $findCol(['phone', 'phone number', 'contact', 'tel', 'លេខទូរស័ព្ទ']),
                    'id_type' => $findCol(['id type', 'identity type', 'document type', 'id_type', 'ប្រភេទឯកសារ']),
                    'id_number' => $findCol(['id number', 'identity id', 'document id', 'id no', 'id_number', 'លេខឯកសារ']),
                    'id_expiry' => $findCol(['id expiry', 'identity expiry', 'expire', 'expiry', 'id_expiry', 'ថ្ងៃផុតកំណត់']),
                    'occupation' => $findCol(['occupation', 'job', 'work', 'មុខរបរ']),
                    'marital_status' => $findCol(['marital status', 'marital_status', 'status', 'ស្ថានភាពគ្រួសារ']),
                    'village' => $findCol(['village', 'phum', 'ភូមិ']),
                    'commune' => $findCol(['commune', 'khum', 'ឃុំ', 'សង្កាត់']),
                    'district' => $findCol(['district', 'srok', 'ស្រុក', 'ខណ្ឌ']),
                    'province' => $findCol(['province', 'khet', 'city', 'ខេត្ត', 'រាជធានី']),
                    'id_issued_date' => $findCol(['issue', 'issued', 'dated', 'identity issued', 'id_issue_date', 'ថ្ងៃចេញឯកសារ']),
                ];

                // Check if we actually found at least some common headers
                if ($tempMap['first_name'] !== -1 || $tempMap['last_name'] !== -1 || $tempMap['phone'] !== -1) {
                    $hasHeaders = true;
                    $colMap = $tempMap;
                } else {
                    // Not a header row, so put it back
                    array_unshift($rows, $headers);
                }
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

                $getVal = function ($key) use ($row, $colMap) {
                    $idx = $colMap[$key] ?? -1;
                    $val = $idx >= 0 ? trim((string) ($row[$idx] ?? '')) : '';
                    return $val === '' ? null : $val;
                };

                $firstName = $getVal('first_name');
                $lastName = $getVal('last_name');
                $latinName = $getVal('latin_name');

                if (empty($firstName) || empty($lastName)) {
                    $errors[] = "Row " . ($index + ($hasHeaders ? 2 : 1)) . ": First Name and Last Name are required.";
                    continue;
                }

                $currentSequence++;
                $separator = ($prefix === 'INV') ? '' : '-';
                $customerCode = $prefix . $separator . str_pad($currentSequence, 3, '0', STR_PAD_LEFT);

                $genderVal = strtoupper($getVal('gender') ?? '');
                $gender = match (true) {
                    in_array($genderVal, ['F', 'FEMALE', 'ស្រី']) => 'Female',
                    in_array($genderVal, ['M', 'MALE', 'ប្រុស']) => 'Male',
                    default => 'Other'
                };

                $phone = $getVal('phone');
                if (!empty($phone)) {
                    // Remove all non-digits except +
                    $phone = preg_replace('/[^0-9+]/', '', $phone);
                    if (strlen($phone) > 0 && $phone[0] !== '0' && $phone[0] !== '+') {
                        if (str_starts_with($phone, '855')) {
                            $phone = '+' . $phone;
                        } else {
                            $phone = '0' . $phone;
                        }
                    }
                }

                $idNumber = $getVal('id_number');

                // Always handle duplicate id_number to prevent SQL unique constraint errors
                if (!empty($idNumber)) {
                    $modelClass = $this->getModel($type);
                    if ($modelClass && $modelClass::where('id_number', $idNumber)->exists()) {
                        if ($force) {
                            // Also suffix the ORIGINAL record in DB so both show as duplicates
                            $originalRecord = $modelClass::where('id_number', $idNumber)->first();
                            if ($originalRecord && !preg_match('/d\d+$/', $originalRecord->id_number)) {
                                // Rename original to d1
                                $originalRecord->id_number = $idNumber . 'd1';
                                $originalRecord->save();
                                // New row gets d2
                                $idNumber = $idNumber . 'd2';
                            } else {
                                // Original already suffixed, find next available suffix
                                $suffix = 1;
                                while ($modelClass::where('id_number', $idNumber . 'd' . $suffix)->exists()) {
                                    $suffix++;
                                }
                                $idNumber = $idNumber . 'd' . $suffix;
                            }
                        } else {
                            // Skip this row - should have been caught by checkDuplicates
                            $errors[] = "Row " . ($index + ($hasHeaders ? 2 : 1)) . ": ID Number '$idNumber' already exists.";
                            $currentSequence--; // Revert the sequence increment
                            continue;
                        }
                    }
                }

                $data = [
                    'row_no' => $currentSequence,
                    'customer_code' => $customerCode,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'latin_name' => $latinName,
                    'gender' => $gender,
                    'dob' => $this->parseDate($getVal('dob')),
                    'phone' => $phone,
                    'id_type' => $getVal('id_type') ?: 'National ID',
                    'id_number' => $idNumber,
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

    /**
     * Check for duplicate id_numbers in the Excel file against existing DB records.
     * Returns list of duplicates without saving anything.
     */
    public function checkDuplicates(string $filePath, string $type): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Extract headers and map indices (same logic as import)
            $headers = [];
            if (count($rows) > 0) {
                $headers = array_map(function ($h) {
                    return strtolower(trim((string) $h)); }, $rows[0]);
                array_shift($rows);
            }

            $colMap = [
                'first_name' => 0,
                'last_name' => 1,
                'latin_name' => 2,
                'gender' => 3,
                'dob' => 4,
                'phone' => 5,
                'id_type' => 6,
                'id_number' => 7,
                'id_expiry' => 8,
                'occupation' => 9,
                'marital_status' => 10,
                'village' => 11,
                'commune' => 12,
                'district' => 13,
                'province' => 14,
                'id_issued_date' => -1,
            ];

            $hasHeaders = false;
            if (!empty($headers)) {
                $findCol = function ($names) use ($headers) {
                    foreach ((array) $names as $name) {
                        $search = strtolower($name);
                        foreach ($headers as $idx => $header) {
                            if (str_contains($header, $search)) {
                                return $idx;
                            }
                        }
                    }
                    return -1;
                };

                $tempMap = [
                    'first_name' => $findCol(['first name', 'firstname', 'first_name', 'name', 'នាមខ្លួន']),
                    'last_name' => $findCol(['last name', 'lastname', 'last_name', 'surname', 'នាមត្រកូល']),
                    'id_number' => $findCol(['id number', 'identity id', 'document id', 'id no', 'id_number', 'លេខឯកសារ']),
                ];

                if ($tempMap['first_name'] !== -1 || $tempMap['last_name'] !== -1) {
                    $hasHeaders = true;
                    $colMap = array_merge($colMap, $tempMap);
                } else {
                    array_unshift($rows, $headers);
                }
            }

            $modelClass = $this->getModel($type);
            if (!$modelClass) {
                return ['has_duplicates' => false, 'duplicates' => []];
            }

            $duplicates = [];
            $seenIdNumbers = []; // Track within-file duplicates

            foreach ($rows as $index => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }

                $idIdx = $colMap['id_number'] ?? -1;
                $idNumber = $idIdx >= 0 ? trim((string) ($row[$idIdx] ?? '')) : '';

                if (!empty($idNumber)) {
                    $rowNum = $index + ($hasHeaders ? 2 : 1);

                    // Check against existing DB records
                    $existing = $modelClass::where('id_number', $idNumber)->first();
                    if ($existing) {
                        $duplicates[] = [
                            'row' => $rowNum,
                            'id_number' => $idNumber,
                            'existing_code' => $existing->customer_code ?? 'N/A',
                        ];
                    }

                    // Check within-file duplicates
                    if (isset($seenIdNumbers[$idNumber])) {
                        $duplicates[] = [
                            'row' => $rowNum,
                            'id_number' => $idNumber,
                            'existing_code' => 'Row ' . $seenIdNumbers[$idNumber] . ' (same file)',
                        ];
                    } else {
                        $seenIdNumbers[$idNumber] = $rowNum;
                    }
                }
            }

            return [
                'has_duplicates' => count($duplicates) > 0,
                'duplicates' => $duplicates,
            ];

        } catch (\Exception $e) {
            Log::error("Duplicate Check Error: " . $e->getMessage());
            return ['has_duplicates' => false, 'duplicates' => [], 'error' => $e->getMessage()];
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
