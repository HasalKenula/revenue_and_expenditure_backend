<?php

namespace App\Imports;

use App\Models\Budget;  // Change this to Budget (not BudgetRecord)
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class BudgetImport implements ToModel, WithHeadingRow, WithCalculatedFormulas, SkipsEmptyRows
{
    private $importedCount = 0;
    private $skippedCount = 0;
    
    public function model(array $row)
    {
        // Skip if row is completely empty
        if (!array_filter($row)) {
            $this->skippedCount++;
            return null;
        }
        
        // Get the amount field (case insensitive)
        $amount = $row['amount'] ?? $row['AMOUNT'] ?? null;
        
        // Remove Excel formulas
        if (is_string($amount)) {
            $amount = trim($amount);
            if (str_starts_with($amount, '=')) {
                $amount = null;
            }
        }
        
        // Skip if amount is null, zero, or "0.00"
        if (is_null($amount) || $amount == 0 || $amount == '0' || $amount == '0.00') {
            $this->skippedCount++;
            return null;
        }
        
        // Get head field
        $head = $row['head'] ?? $row['HEAD'] ?? null;
        
        // Get other fields
        $program = $row['program'] ?? $row['PROGRAM'] ?? null;
        $project = $row['project'] ?? $row['PROJECT'] ?? null;
        $subproj = $row['subproj'] ?? $row['SUBPROJ'] ?? null;
        $object = $row['object'] ?? $row['OBJECT'] ?? null;
        $objDetail = $row['obj_detail'] ?? $row['OBJ_DETAIL'] ?? null;
        $funding = $row['funding'] ?? $row['FUNDING'] ?? null;
        $objname = $row['objname'] ?? $row['OBJNAME'] ?? null;
        
        // Convert amount to numeric
        $numericAmount = is_numeric($amount) ? floatval($amount) : null;
        
        if ($numericAmount === null || $numericAmount <= 0) {
            $this->skippedCount++;
            return null;
        }
        
        $this->importedCount++;
        
        // Use Budget model (not BudgetRecord)
        return new Budget([
            'head'       => $head,
            'program'    => $program,
            'project'    => $project,
            'subproj'    => $subproj,
            'object'     => $object,
            'obj_detail' => $objDetail,
            'funding'    => $funding,
            'objname'    => $objname,
            'amount'     => $numericAmount,
        ]);
    }
    
    public function getImportedCount()
    {
        return $this->importedCount;
    }
    
    public function getSkippedCount()
    {
        return $this->skippedCount;
    }
}