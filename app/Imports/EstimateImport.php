<?php

namespace App\Imports;

use App\Models\Estimate;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class EstimateImport implements ToModel, SkipsEmptyRows, WithCalculatedFormulas
{
    private $importedCount = 0;
    private $skippedCount = 0;

    public function model(array $row)
    {
        // Check if row has data
        if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
            $this->skippedCount++;
            return null;
        }

        $this->importedCount++;

        return new Estimate([
            'head' => $row[0] ?? null,
            'program' => $row[1] ?? null,
            'project' => $row[2] ?? null,
            'sub_project' => $row[3] ?? null,
            'object' => $row[4] ?? null,
            'revenue_code_name' => $row[5] ?? null,
            'estimate' => $row[6] ?? null,
            're_estimate' => $row[7] ?? null,
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