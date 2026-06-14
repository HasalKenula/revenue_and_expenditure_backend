<?php

namespace App\Imports;

use App\Models\OpeningBalance;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class OpeningBalanceImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, WithCalculatedFormulas
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
        if (empty($row['head']) && (empty($row['opening_balance']) || $row['opening_balance'] == 0)) {
            $this->skippedCount++;
            return null;
        }

        $this->importedCount++;

        return new OpeningBalance([
            'head' => !empty($row['head']) ? (int)$row['head'] : null,
            'year' => !empty($row['year']) ? (int)$row['year'] : null,
            'opening_balance' => !empty($row['opening_balance']) ? floatval($row['opening_balance']) : 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'head' => 'nullable|integer',
            'year' => 'nullable|integer|digits:4',
            'opening_balance' => 'nullable|numeric',
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