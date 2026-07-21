<?php
namespace App\Imports;

use App\Models\UserMonthlyFinance;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class UserMonthlyFinanceImport implements ToModel, SkipsEmptyRows, WithCalculatedFormulas
{
    private $userId;
    private $username;
    private $importedCount = 0;
    private $skippedCount = 0;

    public function __construct($userId, $username)
    {
        $this->userId = $userId;
        $this->username = $username;
    }

    public function model(array $row)
    {
        // Skip header row if needed
        if (isset($row[0]) && strtolower($row[0]) === 'subject') {
            return null;
        }

        return new UserMonthlyFinance([
            'user_id' => $this->userId,
            'username' => $this->username, // Store username
            'subject' => $row[0] ?? null,
            'trno' => $row[1] ?? null,
            'month' => $row[2] ?? null,
            'sn' => $row[3] ?? null,
            'dr_cr_code' => $row[4] ?? null,
            'head' => $row[5] ?? null,
            'program' => $row[6] ?? null,
            'project' => $row[7] ?? null,
            'sub_project' => $row[8] ?? null,
            'object' => $row[9] ?? null,
            'item' => $row[10] ?? null,
            'funding' => $row[11] ?? null,
            'dr_cr' => $row[12] ?? null,
            'cash_xe' => $row[13] ?? 0,
            'head_no' => $row[14] ?? null,
            'year' => $row[15] ?? null,
            'cash' => $row[16] ?? 0,
            'xe' => $row[17] ?? 0,
            'is_approved' => false,
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
