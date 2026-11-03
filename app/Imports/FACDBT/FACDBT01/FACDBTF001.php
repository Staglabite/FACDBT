<?php

namespace App\Imports\FACDBT\FACDBT01;

use App\Models\FACDBT01\FACDBT_MSHLOCATION as Location;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

/**
 * File dibaca per CHUNK_SIZE baris (WithChunkReading) supaya file besar tidak menghabiskan memori PHP;
 * collection() dipanggil sekali per chunk, nomor baris Excel dilanjutkan lewat $nextRow.
 */
class FACDBTF001 implements ToCollection, WithHeadingRow, WithStartRow, WithChunkReading
{
    private const CHUNK_SIZE = 1000;

    // Nomor baris Excel berikutnya (null = chunk pertama)
    protected ?int $nextRow = null;

    // [location_no => baris pertama] untuk cek Location No berulang di seluruh file
    protected array $seen = [];

    protected $logs = [];

    protected $totalError = 0;

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

    public function chunkSize(): int
    {
        return self::CHUNK_SIZE;
    }

    public function collection(Collection $collection): void
    {
        if ($this->nextRow === null && ($collection === null || $collection->isEmpty())) {
            throw new SpreadsheetException('The file is empty', 422);
        }

        $currentRowIndex = $this->nextRow ?? $this->startRow();

        foreach ($collection as $row) {
            // Baris kosong (sisa format di Excel) dilewati, nomor baris tetap mengikuti Excel
            if (collect($row)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                $currentRowIndex++;
                continue;
            }

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
                if (isset($this->seen[$key])) {
                    $this->setLog($row, "Duplicate location_no {$row['location_no']} found in row {$this->seen[$key]}.");
                } else {
                    $this->seen[$key] = $currentRowIndex;
                }
            }

            $currentRowIndex++;
        }

        $this->nextRow = $currentRowIndex;
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
        // // Check if the location_no already exists in the FACDBT connection
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