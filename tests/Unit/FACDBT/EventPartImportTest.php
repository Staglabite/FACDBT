<?php

namespace Tests\Unit\FACDBT;

use App\Imports\FACDBT\FACDBT01\FACDBTF006 as Import;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Import daftar part Pre-Taking: Part No & Location No wajib, pasangan berulang diambil sekali.
 */
class EventPartImportTest extends TestCase
{
    private function import(array $rows): Import
    {
        $import = new Import;
        $import->collection(new Collection(array_map(fn ($r) => new Collection($r), $rows)));

        return $import;
    }

    public function test_part_and_location_are_required(): void
    {
        $import = $this->import([
            ['part_no' => 'P1', 'location_no' => 'IN024'],
            ['part_no' => 'P1', 'location_no' => 'IN025'],
            ['part_no' => 'p1 ', 'location_no' => 'in024'],
            ['part_no' => '', 'location_no' => ''],
            ['part_no' => 'P2', 'location_no' => ''],
            ['part_no' => '', 'location_no' => 'IN024'],
        ]);

        $this->assertTrue($import->hasColumns());
        $this->assertSame([['P1', 'IN024'], ['P1', 'IN025']], array_values($import->parts()));
        $this->assertSame([[6, ['Location No is required.']], [7, ['Part No is required.']]],
            array_map(fn ($e) => [$e['row'], $e['errors']], $import->errors()));
    }

    public function test_template_without_location_column_is_rejected(): void
    {
        $import = $this->import([['part_no' => 'P1']]);

        $this->assertFalse($import->hasColumns());
        $this->assertSame([], $import->parts());
    }
}
