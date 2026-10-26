<?php

namespace Tests\Unit\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD001;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Jam slot chart Counting Progress / Absolute Variance (00:00, lalu per 3 jam 07:30 s/d 22:00) dan
 * Counting Progress Oil (00:00, 10:00, 14:00, 18:00), berulang setiap hari taking.
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
            '06 00:00', '06 07:30', '06 10:30', '06 13:30', '06 16:30', '06 19:30', '06 22:00',
            '07 00:00', '07 07:30', '07 10:30', '07 13:30', '07 16:30', '07 19:30', '07 22:00',
        ], $this->times($plan, 'non_oil'));

        $this->assertSame([
            '06 00:00', '06 10:00', '06 14:00', '06 18:00',
            '07 00:00', '07 10:00', '07 14:00', '07 18:00',
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

        $this->assertCount(7, $plan['non_oil']);
        $this->assertCount(4, $plan['oil']);
    }
}
