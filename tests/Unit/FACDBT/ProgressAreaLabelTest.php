<?php

namespace Tests\Unit\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD001;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Aturan label area chart "Counting Progress by Part / Oil Area" di Main Dashboard (FACDBTD001).
 */
class ProgressAreaLabelTest extends TestCase
{
    public static function labels(): array
    {
        return [
            'non-oil memakai location_group'             => ['L-001', 'PROD-1B', false, 'PROD-1B'],
            'non-oil di lokasi PPC tetap group-nya'      => ['PPC023', 'PPC-D', false, 'PPC-D'],
            'non-oil tanpa group'                         => ['L-404', null, false, 'UNKNOWN'],
            'oil diberi prefix OIL'                       => ['L-001', 'PROD-1B', true, 'OIL PROD-1B'],
            'oil di group yang sudah berawalan OIL'       => ['L-002', 'OIL GD. A', true, 'OIL GD. A'],
            'oil di group berawalan oil (huruf kecil)'    => ['L-002', 'oil gd. e', true, 'oil gd. e'],
            'oil di lokasi PPC digabung ke PPC-OIL'       => ['ppc-f-pipa', 'PROD-2A', true, 'PPC-OIL'],
            'oil di group PPC digabung ke PPC-OIL'        => ['L-003', 'PPC-E', true, 'PPC-OIL'],
            'oil tanpa group'                             => ['L-404', null, true, 'OIL UNKNOWN'],
        ];
    }

    #[DataProvider('labels')]
    public function test_area_label(string $location, ?string $area, bool $isOil, string $expected): void
    {
        $this->assertSame($expected, FACDBTD001::areaLabel($location, $area, $isOil));
    }

    public function test_group_book_by_area(): void
    {
        $book = [
            (object) ['LOCATION_NO' => ' L1 ', 'PART_NO' => 'P1', 'qty' => 10], // spasi di LOCATION_NO tetap terpetakan
            (object) ['LOCATION_NO' => 'L2', 'PART_NO' => 'P1', 'qty' => 5],
            (object) ['LOCATION_NO' => 'L2', 'PART_NO' => 'OIL1', 'qty' => 3],
            (object) ['LOCATION_NO' => 'L3', 'PART_NO' => 'P2', 'qty' => 7],  // lokasi di dua group
            (object) ['LOCATION_NO' => 'L9', 'PART_NO' => 'P3', 'qty' => 2],  // lokasi tanpa group
        ];
        $oilParts = ['OIL1' => 1, 'P1' => 0];
        $areaMap  = ['L1' => ['PROD-1A'], 'L2' => ['PROD-1A'], 'L3' => ['PROD-1A', 'PROD-2B']];

        $rows = [];
        foreach (FACDBTD001::groupBookByArea($book, $oilParts, $areaMap) as $r) {
            $rows[$r->area_label][$r->PART_NO] = $r->qty_book;
        }

        $this->assertEquals([
            'PROD-1A'     => ['P1' => 15, 'P2' => 7],
            'OIL PROD-1A' => ['OIL1' => 3],
            'PROD-2B'     => ['P2' => 7],
            'UNKNOWN'     => ['P3' => 2],
        ], $rows);
    }
}
