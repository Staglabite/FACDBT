<?php

namespace App\Imports\FACDBT\FACDBT01;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class FACDBTF005 implements ToCollection, WithHeadingRow, WithStartRow
{
    protected $logs = [];

    protected $totalError = 0;

    protected $createdBy = null;

    // Kolom hasil heading row Excel (slug dari heading template)
    protected $columns = [
        'location_no',
        'id_loc',
        'location_group',
    ];

    // Mapping kolom Excel ke kolom tabel SIM_INV_GROUP_LOCATION
    protected $columnMap = [
        'location_no' => 'LOCATION_NO',
        'id_loc' => 'ID_LOC',
        'location_group' => 'location_group',
    ];

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
            $this->required($row, $this->columns);
            $this->maxLength($row, $this->columns, 50);

            $key = implode('|', Arr::only($row, $this->columns));
            if (! empty($row['location_no'])) {
                if (isset($seen[$key])) {
                    $this->setLog($row, "Duplicate group location {$row['location_no']} found in row {$seen[$key]}.");
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
                $data = [];
                foreach ($this->columnMap as $excelColumn => $dbColumn) {
                    $data[$dbColumn] = $item[$excelColumn];
                }

                return $data;
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

    public function maxLength($row, $columns, $max)
    {
        foreach ($columns as $col) {
            if (isset($row[$col]) && mb_strlen($row[$col]) > $max) {
                $this->setLog($row, "The {$col} field must not be greater than {$max} characters.");
            }
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
