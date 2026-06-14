<?php

namespace App\Imports;

use App\Models\ImpressSettlement;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class ImpressSettlementImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, WithCalculatedFormulas
{
    private $importedCount = 0;
    private $skippedCount = 0;
    private $errors = [];

    public function model(array $row)
    {
        // Check if row has any data
        $hasData = false;
        foreach ($row as $value) {
            if (!empty($value)) {
                $hasData = true;
                break;
            }
        }
        
        if (!$hasData) {
            $this->skippedCount++;
            return null;
        }

        // Skip if no head and no amount
        if (empty($row['head']) && (empty($row['amount']) || $row['amount'] == 0)) {
            $this->skippedCount++;
            return null;
        }

        $this->importedCount++;

        return new ImpressSettlement([
            'head' => !empty($row['head']) ? (int)$row['head'] : null,
            'year' => !empty($row['year']) ? (int)$row['year'] : null,
            'month' => !empty($row['month']) ? (int)$row['month'] : null,
            'amount' => !empty($row['amount']) ? floatval($row['amount']) : 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'head' => 'nullable|integer',
            'year' => 'nullable|integer|digits:4',
            'month' => 'nullable|integer|between:1,12',
            'amount' => 'nullable|numeric|min:0',
        ];
    }

    public function getImportedCount()
    {
        return $this->importedCount;
    }

    public function getSkippedCount()
    {
        return $this->skippedCount;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}