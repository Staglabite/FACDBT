<?php

namespace App\Imports\FACDBT\FACDBT01;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

/**
 * Daftar part Pre-Taking (Master Event): satu kolom "Part No". Part kosong dilewati, part berulang diambil sekali.
 */
class FACDBTF006 implements ToCollection, WithHeadingRow
{
    public const MAX_LENGTH = 100;

    /** @var array<string, true> */
    private array $parts = [];

    /** @var array<int, array{row: int, part_no: string, errors: array<int, string>}> */
    private array $errors = [];

    private bool $hasColumn = true;

    public function collection(Collection $collection): void
    {
        if ($collection->isEmpty()) {
            throw new SpreadsheetException('The file is empty', 422);
        }

        if (!$collection->first()->has('part_no')) {
            $this->hasColumn = false;

            return;
        }

        // Baris 1 = judul kolom
        $rowNumber = 2;
        foreach ($collection as $row) {
            $part = trim((string) ($row['part_no'] ?? ''));

            if ($part !== '') {
                if (mb_strlen($part) > self::MAX_LENGTH) {
                    $this->errors[] = ['row' => $rowNumber, 'part_no' => $part, 'errors' => ['Part No may not be greater than ' . self::MAX_LENGTH . ' characters.']];
                } else {
                    $this->parts[$part] = true;
                }
            }

            $rowNumber++;
        }
    }

    public function hasPartColumn(): bool
    {
        return $this->hasColumn;
    }

    /**
     * @return array<int, string>
     */
    public function parts(): array
    {
        return array_map('strval', array_keys($this->parts));
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
