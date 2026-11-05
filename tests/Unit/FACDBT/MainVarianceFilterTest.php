<?php

namespace Tests\Unit\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD001;
use PHPUnit\Framework\TestCase;

/**
 * Filter interaktif chart variance Main Dashboard (FACDBTD001::varianceSummary).
 */
class MainVarianceFilterTest extends TestCase
{
    // [labels, seg, acc, nett, absolute]
    private const ROWS = [
        [['PPC-D'], '4W', 'RM', -10.0, 30.0],
        [['PPC-D'], '2W', 'FG', 5.0, 5.0],
        [['PROD-1B', 'PROD-2A'], '4W', 'FG', -2.0, 4.0],
    ];

    private function summary(?string $area = null, ?string $seg = null, ?string $acc = null): array
    {
        $result = FACDBTD001::varianceSummary(self::ROWS, ['area' => $area, 'seg' => $seg, 'acc' => $acc]);
        $result['by_part_group'] = array_column($result['by_part_group'], 'nett', 'group');
        $result['by_accounting_group'] = array_column($result['by_accounting_group'], 'nett', 'group');

        return $result;
    }

    public function test_without_filter(): void
    {
        $r = $this->summary();
        $this->assertSame(-7.0, $r['nett']);
        $this->assertSame(39.0, $r['absolute']);
        $this->assertSame(['4W' => -12.0, '2W' => 5.0], $r['by_part_group']);
    }

    public function test_area_filter_matches_any_label_of_a_location(): void
    {
        $r = $this->summary('PROD-2A');
        $this->assertSame(-2.0, $r['nett']);
        $this->assertSame(['4W' => -2.0], $r['by_part_group']);
    }

    public function test_seg_filter_keeps_all_segs_in_its_own_chart(): void
    {
        $r = $this->summary(null, '4W');
        $this->assertSame(-12.0, $r['nett']);
        // Chart 4W / 2W tetap lengkap (2W dipudarkan di browser)
        $this->assertSame(['4W' => -12.0, '2W' => 5.0], $r['by_part_group']);
        // Kotak RM / WIP / FG hanya 4W
        $this->assertSame(['RM' => -10.0, 'FG' => -2.0], $r['by_accounting_group']);
    }

    public function test_acc_filter(): void
    {
        $r = $this->summary('PPC-D', null, 'FG');
        $this->assertSame(5.0, $r['nett']);
        $this->assertSame(['2W' => 5.0], $r['by_part_group']);
        $this->assertSame(['RM' => -10.0, 'FG' => 5.0], $r['by_accounting_group']);
    }
}
