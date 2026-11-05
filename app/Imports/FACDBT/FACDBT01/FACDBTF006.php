<?php

namespace App\Imports\FACDBT\FACDBT01;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

/**
 * Daftar part Pre-Taking (Master Event): kolom "Part No" dan "Location No", keduanya wajib.
 * Baris yang dua-duanya kosong dilewati, pasangan part + lokasi yang berulang diambil sekali.
 */
class FACDBTF006 implements ToCollection, WithHeadingRow
{
    public const MAX_PART_LENGTH = 100;
    public const MAX_LOCATION_LENGTH = 50;

    /** @var array<string, array{0: string, 1: string}> [kunci => [part_no, location_no]] */
    private array $parts = [];

    /** @var array<int, array{row: int, part_no: string, location_no: string, errors: array<int, string>}> */
    private array $errors = [];

    private bool $hasColumns = true;

    public function collection(Collection $collection): void
    {
        if ($collection->isEmpty()) {
            throw new SpreadsheetException('The file is empty', 422);
        }

        $first = $collection->first();
        if (!$first->has('part_no') || !$first->has('location_no')) {
            $this->hasColumns = false;

            return;
        }

        // Baris 1 = judul kolom
        $rowNumber = 2;
        foreach ($collection as $row) {
            $part     = trim((string) ($row['part_no'] ?? ''));
            $location = trim((string) ($row['location_no'] ?? ''));

            if ($part !== '' || $location !== '') {
                $errors = [];
                if ($part === '') {
                    $errors[] = 'Part No is required.';
                } elseif (mb_strlen($part) > self::MAX_PART_LENGTH) {
                    $errors[] = 'Part No may not be greater than ' . self::MAX_PART_LENGTH . ' characters.';
                }
                if ($location === '') {
                    $errors[] = 'Location No is required.';
                } elseif (mb_strlen($location) > self::MAX_LOCATION_LENGTH) {
                    $errors[] = 'Location No may not be greater than ' . self::MAX_LOCATION_LENGTH . ' characters.';
                }

                if ($errors) {
                    $this->errors[] = ['row' => $rowNumber, 'part_no' => $part, 'location_no' => $location, 'errors' => $errors];
                } else {
                    $this->parts[self::key($part, $location)] ??= [$part, $location];
                }
            }

            $rowNumber++;
        }
    }

    /**
     * Kunci pasangan part + lokasi, tanpa beda huruf besar / kecil & spasi di belakang (sama seperti MySQL).
     */
    public static function key(?string $part, ?string $location): string
    {
        return mb_strtolower(rtrim((string) $part)) . '|' . mb_strtolower(rtrim((string) $location));
    }

    public function hasColumns(): bool
    {
        return $this->hasColumns;
    }

    /**
     * @return array<string, array{0: string, 1: string}> [kunci => [part_no, location_no]]
     */
    public function parts(): array
    {
        return $this->parts;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
