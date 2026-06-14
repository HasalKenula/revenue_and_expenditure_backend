<?php

namespace App\Imports;

use App\Models\MonthlyFincance;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class MonthlyFincanceImport implements ToModel, SkipsEmptyRows, WithCalculatedFormulas

{
     private $importedCount = 0;
    private $skippedCount = 0;

   
    public function model(array $row)
    {
        

        return new MonthlyFincance([
            'subject'        => $row[0],
            'trno'           => $row[1],
            'month'          => $row[2],
            'sn'           => $row[3],
            'dr_cr_code'     => $row[4],
            'head'      => $row[5],
            'program'        => $row[6],
            'project'        => $row[7],
            'sub_project'    => $row[8],
            'object'         => $row[9],
            'item'           => $row[10],
            'funding'        => $row[11],
            'dr_cr'          => $row[12],
            'cash_xe'        => $row[13],
            'head_no'   => $row[14],
            'year' => $row[15],
            'cash'           => $row[16],
            'xe'             => $row[17],
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