<?php

namespace App\Imports\FACDBT\FACDBT01;

use App\Models\FACDBT01\FACDBT_MSHLOCATION as Location;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class FACDBTF001 implements ToCollection, WithHeadingRow, WithStartRow
{
    protected $logs = [];

    protected $totalError = 0;

    protected $createdBy = null;

    protected $columns = [
        'location_no',
        'location_group',
        'site',
        'warehouse',
        'location_description',
        'location_group_description',
    ];

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $collection): void
    {
        if ($collection === null || $collection->isEmpty()) {
            throw new SpreadsheetException('The file is empty', 422);
        }

        // Adjust based on how your system logs the user who created the record.
        // Assuming your Auth::user() structure is similar to the template, adjust 'VUSERNAME' if necessary.
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
            // location_no is typically the primary required field based on your SQL schema
            $this->required($row, ['location_no']); 
            $this->mapRelations($row);

            $key = $row['location_no'];
            if (! empty($key)) {
                if (isset($seen[$key])) {
                    $this->setLog($row, "Duplicate location_no {$row['location_no']} found in row {$seen[$key]}.");
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
        // // Check if the location_no already exists in the INV_TAKING connection
        // $location = Location::where('location_no', $row['location_no'])
        //     ->first();
            
        // if ($location) {
        //     $this->setLog($row, "Location with location_no {$row['location_no']} already exists.");
        // }
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