<?php

namespace Tests\Unit\FACDBT;

use App\Services\FACDBT\AbsoluteVarianceService;
use App\Services\FACDBT\NettVarianceService;
use App\Services\FACDBT\VarianceDashboardService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Rumus Nett & Absolute Variance Dashboard (FACDBTD003 / FACDBTD004) tanpa database:
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

    public function test_nett_variance_tables_are_ordered_by_amount(): void
    {
        $method = new ReflectionMethod(NettVarianceService::class, 'build');
        $result = $method->invoke(new NettVarianceService, [
            ['L1', 'A', 0, 1, 10, 'X'],  // +10
            ['L1', 'B', 0, 1, 50, 'X'],  // +50
            ['L1', 'C', 1, 0, 20, 'X'],  // -20
            ['L1', 'D', 1, 0, 70, 'X'],  // -70
        ], []);

        // Positive dari Var Amount terbesar, negative dari yang paling minus
        $this->assertSame(['B', 'A'], array_column($result['positive']['rows'], 'part_no'));
        $this->assertSame(['D', 'C'], array_column($result['negative']['rows'], 'part_no'));
    }

    public function test_nett_variance_nets_locations_per_part(): void
    {
        $result = $this->build(new NettVarianceService);

        $this->assertEquals(-1, $result['nett_qty']);
        $this->assertEquals(-100, $result['nett_amount']);

        // P1 nett -1 / -100 -> minus; P4 amount 0 tapi qty minus -> minus
        $this->assertSame(['P1', 'P4'], $this->partNos($result['negative']));
        $this->assertEquals(-100, $result['negative']['amount']);

        // P2 pas di semua lokasi (Var Qty 0) tidak ditampilkan; P3 lebih dengan unit cost kosong -> tidak minus
        $this->assertSame(['P3'], $this->partNos($result['positive']));
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
        $this->assertEquals($result['positive']['amount'] - $result['negative']['amount'], $result['absolute_amount']);

        // Nett & nett per area (chart Nett Variance by Area)
        $this->assertEquals(-1, $result['nett_qty']);
        $this->assertEquals(-100, $result['nett_amount']);
        $this->assertSame(
            [['area' => 'A', 'value' => 200.0], ['area' => 'B', 'value' => -300.0]],
            $result['by_area']
        );

        // Satu baris per (part, lokasi): P1 muncul di kedua tabel dengan lokasi berbeda
        $pairs = fn ($detail) => array_map(fn ($r) => $r['part_no'] . '@' . $r['location_no'], $detail['rows']);
        $this->assertSame(['P1@L1', 'P3@L4'], $pairs($result['positive']));
        $this->assertSame(['P1@L2', 'P4@L5'], $pairs($result['negative']));

        // Sisi kurang bertanda minus
        $this->assertEquals(-300, $result['negative']['amount']);
        $this->assertEquals(-6, $result['negative']['qty']);
    }

    public function test_absolute_tables_order_by_part_variance(): void
    {
        $lines = [
            ['part_no' => 'A', 'location_no' => 'L1', 'qty' => 1, 'amount' => 100],
            ['part_no' => 'B', 'location_no' => 'L1', 'qty' => 1, 'amount' => 300],
            ['part_no' => 'A', 'location_no' => 'L2', 'qty' => 1, 'amount' => 250],
            ['part_no' => 'C', 'location_no' => 'L3', 'qty' => 1, 'amount' => 50],
        ];

        // Biru: part dengan total terbesar dulu (A = 350, B = 300, C = 50), lokasi dalam part terbesar dulu
        $rows = AbsoluteVarianceService::locationDetail($lines, [], false)['rows'];
        $this->assertSame(['A@L2', 'A@L1', 'B@L1', 'C@L3'], array_map(fn ($r) => $r['part_no'] . '@' . $r['location_no'], $rows));

        // Merah: part paling minus dulu
        $negative = array_map(fn ($l) => ['qty' => -$l['qty'], 'amount' => -$l['amount']] + $l, $lines);
        $rows = AbsoluteVarianceService::locationDetail($negative, [], true)['rows'];
        $this->assertSame(['A@L2', 'A@L1', 'B@L1', 'C@L3'], array_map(fn ($r) => $r['part_no'] . '@' . $r['location_no'], $rows));
    }

    public function test_nett_hides_parts_without_variance_in_any_location(): void
    {
        $this->lines = [
            ['L1', 'P9', 5, 7, 10, 'A'], ['L2', 'P9', 7, 5, 10, 'A'], // lebih +2 & kurang -2: nett 0, tetap tampil
            ['L1', 'P8', 4, 4, 10, 'A'], ['L2', 'P8', 3, 3, 10, 'A'], // semua lokasi pas: tidak tampil
        ];
        $result = $this->build(new NettVarianceService);

        $this->assertSame(['P9'], $this->partNos($result['positive']));
        $this->assertEquals(0, $result['positive']['rows'][0]['qty']);
        $this->assertSame([], $result['negative']['rows']);
    }

    public function test_unknown_part_name(): void
    {
        $result = $this->build(new NettVarianceService);
        $names  = array_column(array_merge($result['positive']['rows'], $result['negative']['rows']), 'name', 'part_no');

        $this->assertSame('PIPE', $names['P1']);
        $this->assertSame('UNKNOWN', $names['P3']);
    }
}
