<?php

namespace Tests\Unit\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD001;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Jam slot chart Counting Progress / Oil / Absolute Variance (FACDBTD001::slotPlan).
 */
class SlotPlanTest extends TestCase
{
    public function test_default_uses_fixed_slots(): void
    {
        $plan = FACDBTD001::slotPlan(Carbon::parse('2026-03-06'), '2026-03-07 23:59:59', null);

        $this->assertSame('2026-03-06 07:30:00', $plan['times'][1]);
        $this->assertSame('2026-03-07 11:00:00', $plan['times'][8]);
        $this->assertSame([0, 2, 3, 4, 8], $plan['oil']);
    }

    public function test_interval_from_midnight_of_first_day(): void
    {
        $plan = FACDBTD001::slotPlan(Carbon::parse('2026-03-06'), '2026-03-06 23:59:59', 2.5);

        $this->assertSame('2026-03-06 00:00:00', $plan['times'][0]);
        $this->assertSame('2026-03-06 02:30:00', $plan['times'][1]);
        $this->assertSame('2026-03-06 22:30:00', end($plan['times']));
        $this->assertCount(10, $plan['times']);
        $this->assertSame($plan['non_oil'], $plan['oil']);
    }

    public function test_long_period_keeps_last_slots(): void
    {
        $plan = FACDBTD001::slotPlan(Carbon::parse('2026-03-01'), '2026-03-31 23:59:59', 2);

        $this->assertCount(60, $plan['times']);
        $this->assertSame('2026-03-31 22:00:00', end($plan['times']));
    }
}
