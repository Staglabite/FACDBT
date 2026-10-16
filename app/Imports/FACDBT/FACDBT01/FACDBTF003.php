<?php

namespace App\Imports\FACDBT\FACDBT01;

use App\Models\FACDBT01\FACDBT_MSHUNITCOST as UnitCost; // Mengambil model Unit Cost
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class FACDBTF003 implements ToCollection, WithHeadingRow, WithStartRow
{
    protected $logs = [];

    protected $totalError = 0;

    protected $createdBy = null;

    // Disesuaikan dengan field tabel SIM_INV_UNITCOST_TEST (Model FACDBT_MSHUNITCOST)
    protected $columns = [
        'part_no',
        'inventory_part_description',
        'inventory_uom',
        'accounting_group',
        'on_hand_qty',
        'mat_cost',
        'lab_cost',
        'foh_cost',
    ];

    // Judul kolom di template/export (slug) => kolom tabel
    protected $aliases = [
        'description' => 'inventory_part_description',
        'uom' => 'inventory_uom',
        'material_cost' => 'mat_cost',
        'labor_cost' => 'lab_cost',
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
        $this->createdBy = "'" . Auth::user()->VUSERNAME . "'"; 
        $currentRowIndex = $this->startRow();

        foreach ($collection as $row) {
            $row['row'] = $currentRowIndex;
            $row = $row->toArray();

            foreach ($this->aliases as $heading => $column) {
                if (! isset($row[$column]) && isset($row[$heading])) {
                    $row[$column] = $row[$heading];
                }
            }

            foreach ($this->columns as $col) {
                // Parsing nilai angka (cost/qty) untuk menghindari nilai kosong (empty string) masuk ke database sebagai string kosong
                if (in_array($col, ['on_hand_qty', 'mat_cost', 'lab_cost', 'foh_cost'])) {
                     $row[$col] = isset($row[$col]) && $row[$col] !== '' ? (float) trim((string) $row[$col]) : 0;
                } else {
                     $row[$col] = isset($row[$col]) && $row[$col] !== '' ? trim((string) $row[$col]) : null;
                }
            }

            $this->setLog($row);
            
            // part_no adalah primary required field
            $this->required($row, ['part_no']); 
            $this->mapRelations($row);

            // part_no berulang di file tidak dianggap error; baris paling bawah yang dipakai (lihat store())

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
            // Satu baris per part_no; bila berulang, baris paling bawah yang dipakai (menimpa)
            ->keyBy(fn ($item) => mb_strtolower(rtrim((string) $item['part_no'])))
            ->values()
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
        // Relasi dimatikan agar controller bisa menggunakan Upsert (Overwrite) dengan lancar
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