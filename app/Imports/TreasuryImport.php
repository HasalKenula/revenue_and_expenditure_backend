<?php

namespace App\Imports;

use App\Models\Treasury;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class TreasuryImport implements ToModel, SkipsEmptyRows, WithCalculatedFormulas
{
    private $importedCount = 0;
    private $skippedCount = 0;
    private $errors = [];
    private $rowNumber = 0;

    /**
     * Map Excel row to model
     * Expected column positions:
     * 0: subject (default: S)
     * 1: trno (default: 400)
     * 2: month
     * 3: sn
     * 4: dr_cr_code
     * 5: head
     * 6: program
     * 7: project
     * 8: sub_project
     * 9: object
     * 10: item (default: 0)
     * 11: funding (default: 11)
     * 12: dr_cr (DR or CR)
     * 13: cash_xe (default: 0)
     * 14: head_no (default: 400)
     * 15: year (default: 26)
     * 16: cash (default: 0)
     * 17: xe (default: 0)
     */
    public function model(array $row)
    {
        $this->rowNumber++;
        
        // Check if row has any data
        $hasData = false;
        foreach ($row as $value) {
            if (!empty($value) && $value !== null && $value !== '') {
                $hasData = true;
                break;
            }
        }
        
        if (!$hasData) {
            $this->skippedCount++;
            return null;
        }

        $this->importedCount++;

        // Clean DR/CR value
        $drCr = $this->cleanDrCr($row[12] ?? null);

        // Clean and prepare data with default values and proper 0 handling
        return new Treasury([
            'subject' => $this->cleanString($row[0] ?? null) ?? 'S',
            'trno' => $this->cleanInteger($row[1] ?? null) ?? 400,
            'month' => $this->cleanInteger($row[2] ?? null),
            'sn' => $this->cleanString($row[3] ?? null),
            'dr_cr_code' => $this->cleanInteger($row[4] ?? null),
            'head' => $this->cleanInteger($row[5] ?? null),
            'program' => $this->cleanInteger($row[6] ?? null),
            'project' => $this->cleanInteger($row[7] ?? null),
            'sub_project' => $this->cleanInteger($row[8] ?? null),
            'object' => $this->cleanInteger($row[9] ?? null),
            'item' => $this->cleanInteger($row[10] ?? null) ?? 0,
            'funding' => $this->cleanInteger($row[11] ?? null) ?? 11,
            'dr_cr' => $drCr,
            'cash_xe' => $this->cleanDecimal($row[13] ?? null) ?? 0,
            'head_no' => $this->cleanInteger($row[14] ?? null) ?? 400,
            'year' => $this->cleanInteger($row[15] ?? null) ?? 26,
            'cash' => $this->cleanDecimal($row[16] ?? null) ?? 0,
            'xe' => $this->cleanDecimal($row[17] ?? null) ?? 0,
        ]);
    }

    /**
     * Clean DR/CR values - converts D->DR, C->CR
     */
    private function cleanDrCr($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $cleaned = strtoupper(trim((string)$value));
        
        // Convert single letters to full form
        if ($cleaned === 'D') {
            return 'DR';
        }
        if ($cleaned === 'C') {
            return 'CR';
        }
        
        // Return as is if already DR or CR
        if ($cleaned === 'DR' || $cleaned === 'CR') {
            return $cleaned;
        }
        
        return null;
    }

    /**
     * Clean integer values - properly handles 0
     */
    private function cleanInteger($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if ($value === 0 || $value === '0') {
            return 0;
        }
        
        $cleaned = preg_replace('/[^0-9-]/', '', (string)$value);
        
        if (empty($cleaned) && $cleaned !== '0') {
            return null;
        }
        
        return (int)$cleaned;
    }

    /**
     * Clean string values
     */
    private function cleanString($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        return trim((string)$value);
    }

    /**
     * Clean decimal values - properly handles 0
     */
    private function cleanDecimal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if ($value === 0 || $value === '0') {
            return 0;
        }
        
        $cleaned = preg_replace('/[^0-9.-]/', '', (string)$value);
        
        if (empty($cleaned) && $cleaned !== '0') {
            return null;
        }
        
        return (float)$cleaned;
    }

    /**
     * Get imported count
     */
    public function getImportedCount()
    {
        return $this->importedCount;
    }

    /**
     * Get skipped count
     */
    public function getSkippedCount()
    {
        return $this->skippedCount;
    }

    /**
     * Get errors
     */
    public function getErrors()
    {
        return $this->errors;
    }
}