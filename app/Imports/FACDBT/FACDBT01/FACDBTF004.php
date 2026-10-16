<?php

namespace App\Imports\FACDBT\FACDBT01;

use App\Models\FACDBT01\FACDBT_SIMINVTAKINGBOOKTAB as TakingBook; // Mengambil model Taking Book
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class FACDBTF004 implements ToCollection, WithHeadingRow, WithStartRow, WithChunkReading
{
    protected $logs = [];

    protected $totalError = 0;

    protected $createdBy = null;

    // Disimpan di property karena collection() dipanggil sekali per chunk
    protected $currentRowIndex = null;

    protected $seen = [];

    // Disesuaikan dengan field tabel SIM_INV_TAKING_BOOK_TAB (sesuai Model)
    protected $columns = [
        'INV_LIST_NO',
        'SEQ',
        'MONTH',
        'YEAR',
        'CONTRACT',
        'PART_NO',
        'LOCATION_NO',
        'LOT_BATCH_NO',
        'QUANTITY',
        'UNIT_COST',
        'UNIT_MEAS',
        'HANDLING_UNIT_ID',
        'HANDLING_UNIT_TYPE_ID',
        'NPK',
        'VERIFIED',
        'WDR',
        'WAREHOUSE',
        'BAY_NO',
        'ROW_NO',
        'TIER_NO',
        'BIN_NO',
        'AVAILABILITY_CONTROL_ID',
        'AVAILABILITY_CONTROL_DESC',
    ];

    public function startRow(): int
    {
        return 2;
    }

    // File dibaca per 1000 baris supaya file book yang besar tidak dimuat sekaligus ke memori
    public function chunkSize(): int
    {
        return 1000;
    }

    public function collection(Collection $collection): void
    {
        if ($this->currentRowIndex === null) {
            if ($collection === null || $collection->isEmpty()) {
                throw new SpreadsheetException('The file is empty', 422);
            }

            // Ambil User yang sedang login
            $this->createdBy = Auth::user()->VUSERNAME ?? 'SYSTEM';
            $this->currentRowIndex = $this->startRow();
        }

        foreach ($collection as $row) {
            // Baris kosong (sisa format di Excel) dilewati, nomor baris tetap mengikuti Excel
            if (collect($row)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                $this->currentRowIndex++;
                continue;
            }

            // Heading row dibaca sebagai huruf kecil (inv_list_no), sedangkan kolom memakai huruf besar
            $row = array_change_key_case($row->toArray(), CASE_UPPER);
            $row['row'] = $this->currentRowIndex;

            foreach ($this->columns as $col) {
                // Parsing nilai angka (cost/qty/seq/id dll) untuk menghindari error type mismatch
                if (in_array($col, ['SEQ', 'MONTH', 'YEAR', 'QUANTITY', 'UNIT_COST', 'HANDLING_UNIT_ID', 'HANDLING_UNIT_TYPE_ID'])) {
                     $row[$col] = isset($row[$col]) && $row[$col] !== '' ? (float) trim((string) $row[$col]) : 0;
                } else {
                     $row[$col] = isset($row[$col]) && $row[$col] !== '' ? trim((string) $row[$col]) : null;
                }
            }

            $this->setLog($row);
            
            // Kolom yang menjadi Composite Primary Key wajib diisi (Required).
            // INV_LIST_NO tidak wajib karena selalu diganti dengan nomor baru saat disimpan.
            $this->required($row, [
                'SEQ',
                'MONTH',
                'YEAR',
                'CONTRACT',
                'PART_NO',
                'LOCATION_NO',
                'LOT_BATCH_NO'
            ]); 

            // Cek Duplikasi berdasarkan gabungan (Composite Key) tanpa INV_LIST_NO,
            // karena seluruh baris akan memakai INV_LIST_NO yang sama
            $compositeKey = implode('|', [
                $row['SEQ'] ?? '',
                $row['MONTH'] ?? '',
                $row['YEAR'] ?? '',
                $row['CONTRACT'] ?? '',
                $row['PART_NO'] ?? '',
                $row['LOCATION_NO'] ?? '',
                $row['LOT_BATCH_NO'] ?? ''
            ]);
            
            // Pastikan gabungan key tidak kosong sepenuhnya (mencegah validasi baris kosong)
            if ($compositeKey !== '||||||') {
                if (isset($this->seen[$compositeKey])) {
                    $this->setLog($row, "Duplicate primary key combination found in row {$this->seen[$compositeKey]}.");
                } else {
                    $this->seen[$compositeKey] = $this->currentRowIndex;
                }
            }

            $this->currentRowIndex++;
        }
    }

    public function store()
    {
        return collect($this->logs)
            ->where('error_count', 0)
            ->values()
            ->map(function ($item) {
                // Menambahkan format created by dan modified by (karena tabel ini menggunakannya)
                $data = Arr::only($item, $this->columns);
                $data['CREATED_BY'] = $this->createdBy;
                $data['MODIFIED_BY'] = $this->createdBy;
                // Created_datetime dan Modified_datetime akan di-handle oleh parameter DB::upsert di controller 
                // atau model jika di-set secara otomatis.
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