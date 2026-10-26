<?php

namespace Tests\Unit\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD001;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Jam slot chart Counting Progress / Absolute Variance (per 4 jam) dan Counting Progress Oil (per 5 jam).
 */
class SlotPlanTest extends TestCase
{
    private function times(array $plan, string $group): array
    {
        return array_map(fn ($i) => substr($plan['times'][$i], 8, 8), $plan[$group]);
    }

    public function test_two_day_taking(): void
    {
        $plan = FACDBTD001::slotPlan(Carbon::parse('2026-03-06'), '2026-03-07 23:59:59');

        $this->assertSame([
            '06 00:00', '06 04:00', '06 08:00', '06 12:00', '06 16:00', '06 20:00',
            '07 00:00', '07 04:00', '07 08:00', '07 12:00', '07 16:00', '07 20:00',
        ], $this->times($plan, 'non_oil'));

        // Oil per 5 jam, setiap hari mulai lagi dari 00:00
        $this->assertSame([
            '06 00:00', '06 05:00', '06 10:00', '06 15:00', '06 20:00',
            '07 00:00', '07 05:00', '07 10:00', '07 15:00', '07 20:00',
        ], $this->times($plan, 'oil'));

        // times urut dan tanpa duplikat
        $sorted = $plan['times'];
        sort($sorted);
        $this->assertSame($sorted, $plan['times']);
        $this->assertSame(count(array_unique($plan['times'])), count($plan['times']));
    }

    public function test_one_day_taking_only_has_day_one(): void
    {
        $plan = FACDBTD001::slotPlan(Carbon::parse('2026-03-06'), '2026-03-06 23:59:59');

        $this->assertCount(6, $plan['non_oil']);
        $this->assertCount(5, $plan['oil']);
    }
}
