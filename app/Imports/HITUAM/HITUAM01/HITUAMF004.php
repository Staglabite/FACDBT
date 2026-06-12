<?php

namespace App\Imports\HITUAM\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class HITUAMF004 implements ToCollection, WithHeadingRow, WithStartRow
{
    protected $logs = [];

    protected $totalError = 0;

    protected $createdBy = null;

    protected $columns = ['role_name', 'description'];

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $collection)
    {
        if ($collection === null || $collection->isEmpty()) {
            throw new SpreadsheetException('The file is empty', 422);
        }

        $this->createdBy = "'" . Auth::user()->VUSERNAME . "'";
        $currentRowIndex = $this->startRow();
        $seen = [];

        foreach ($collection as $row) {
            $row['row'] = $currentRowIndex;
            $row = $row->toArray();

            foreach ($this->columns as $col) {
                $row[$col] = isset($row[$col]) && $row[$col] !== '' ? trim((string) $row[$col]) : null;
            }

            $this->setLog($row);
            $this->required($row, ['role_name', 'description']);
            $this->mapRelations($row);

            $key = $row['role_name'];
            if (! empty($key)) {
                if (isset($seen[$key])) {
                    $this->setLog($row, "Duplicate role_name {$row['role_name']} found in row {$seen[$key]}.");
                } else {
                    $seen[$key] = $currentRowIndex;
                }
            }

            $currentRowIndex++;
        }
    }

    public function store()
    {
        return collect($this->logs)
            ->where('error_count', 0)
            ->values()
            ->map(function ($item) {
                return Arr::only($item, $this->columns);
            })
            ->toArray();
    }

    public function required($row, $columns)
    {
        $emptyColumns = array_filter($columns, fn($column) => ! isset($row[$column]) || $row[$column] === null || $row[$column] === '');

        foreach ($emptyColumns as $col) {
            $this->setLog($row, "The {$col} field is required.");
        }
    }

    protected function setLog($row, $error = null)
    {
        $unique = $row['row'];
        if (! isset($this->logs[$unique])) {
            $this->logs[$unique] = array_merge(Arr::only($row, $this->columns), [
                'row' => $unique,
                'error_count' => 0,
                'errors' => [],
                'created_by' => $this->createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($error !== null) {
            $this->totalError++;
            $this->logs[$unique]['error_count']++;
            $this->logs[$unique]['errors'][] = $error;
        }
    }

    public function mapRelations($row)
    {
        $role = Role::where('VROLENAME', $row['role_name'])
            ->whereNull('DDELETE')
            ->first();
        if ($role) {
            $this->setLog($row, "Role with name {$row['role_name']} already exists.");
        }
    }

    public function getResult()
    {
        $correctLogs = collect($this->logs)->where('error_count', '=', 0)->count();
        $incorrectLogs = collect($this->logs)->where('error_count', '>', 0)->count();
        $errorLogs = collect($this->logs)->where('error_count', '>', 0);

        return (object) [
            'totalLogs' => count($this->logs),
            'totalCorrectLogs' => $correctLogs,
            'totalErrorLogs' => $incorrectLogs,
            'totalError' => $this->totalError,
            'logs' => array_values($this->logs),
            'errorLogs' => $errorLogs,
        ];
    }
}
