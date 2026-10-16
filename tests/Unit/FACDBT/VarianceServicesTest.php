<?php

namespace Tests\Unit\FACDBT;

use App\Services\FACDBT\AbsoluteVarianceService;
use App\Services\FACDBT\NettVarianceService;
use App\Services\FACDBT\VarianceDashboardService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Rumus Nett & Absolute Variance Dashboard (FACDBTD004 / FACDBTD005) tanpa database:
 * build() menerima variance per (lokasi, part) yang sudah difilter area.
 */
class VarianceServicesTest extends TestCase
{
    /**
     * [location_no, part_no, qty_book, qty_taking, unit_cost, area]
     */
    private array $lines = [
        ['L1', 'P1', 10, 12, 100, 'A'], // P1 lebih 2 di L1 (+200)
        ['L2', 'P1', 5, 2, 100, 'B'],   // P1 kurang 3 di L2 (-300)
        ['L3', 'P2', 4, 4, 50, 'A'],    // P2 pas (0)
        ['L4', 'P3', 0, 3, 0, 'B'],     // P3 lebih 3, unit cost kosong
        ['L5', 'P4', 8, 5, 0, 'A'],     // P4 kurang 3, unit cost kosong
    ];

    private array $names = ['P1' => 'PIPE', 'P2' => 'SEAL'];

    private function build(VarianceDashboardService $service): array
    {
        $method = new ReflectionMethod($service, 'build');

        return $method->invoke($service, $this->lines, $this->names);
    }

    private function partNos(array $detail): array
    {
        $partNos = array_column($detail['rows'], 'part_no');
        sort($partNos);

        return $partNos;
    }

    public function test_nett_variance_nets_locations_per_part(): void
    {
        $result = $this->build(new NettVarianceService);

        $this->assertEquals(-1, $result['nett_qty']);
        $this->assertEquals(-100, $result['nett_amount']);

        // P1 nett -1 / -100 -> minus; P4 amount 0 tapi qty minus -> minus
        $this->assertSame(['P1', 'P4'], $this->partNos($result['negative']));
        $this->assertEquals(-100, $result['negative']['amount']);

        // P2 pas (0) & P3 lebih dengan unit cost kosong -> tidak minus
        $this->assertSame(['P2', 'P3'], $this->partNos($result['positive']));
        $this->assertEquals(0, $result['positive']['amount']);

        // by area: A = +200 + 0 + 0, B = -300 + 0; urut dari terbesar
        $this->assertSame(
            [['area' => 'A', 'value' => 200.0], ['area' => 'B', 'value' => -300.0]],
            $result['by_area']
        );
    }

    public function test_absolute_variance_does_not_net_locations(): void
    {
        $result = $this->build(new AbsoluteVarianceService);

        // |+200| + |-300| dan qty |2| + |3| + |3| + |3|
        $this->assertEquals(500, $result['absolute_amount']);
        $this->assertEquals(11, $result['absolute_qty']);
        $this->assertEquals($result['positive']['amount'] + $result['negative']['amount'], $result['absolute_amount']);

        // P1 muncul di kedua sisi (lebih di L1, kurang di L2); part tanpa selisih tidak ditampilkan
        $this->assertSame(['P1', 'P3'], $this->partNos($result['positive']));
        $this->assertSame(['P1', 'P4'], $this->partNos($result['negative']));
        $this->assertEquals(300, $result['negative']['amount']);

        // nilai sisi kurang ditampilkan absolut
        foreach ($result['negative']['rows'] as $row) {
            $this->assertGreaterThanOrEqual(0, $row['qty']);
            $this->assertGreaterThanOrEqual(0, $row['amount']);
        }

        $this->assertSame(
            [['area' => 'B', 'value' => 300.0], ['area' => 'A', 'value' => 200.0]],
            $result['by_area']
        );
    }

    public function test_unknown_part_name(): void
    {
        $result = $this->build(new NettVarianceService);
        $names  = array_column($result['positive']['rows'], 'name', 'part_no');

        $this->assertSame('SEAL', $names['P2']);
        $this->assertSame('UNKNOWN', $names['P3']);
    }
}
