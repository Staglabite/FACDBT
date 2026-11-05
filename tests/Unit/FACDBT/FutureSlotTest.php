<?php

namespace Tests\Unit\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD001;
use PHPUnit\Framework\TestCase;

/**
 * Slot waktu chart Main Dashboard yang jamnya belum tiba dibuat 0 (FACDBTD001::zeroFutureSlots).
 */
class FutureSlotTest extends TestCase
{
    public function test_slots_after_now_are_zero(): void
    {
        $series = [
            ['time' => '2026-03-06 07:30:00', 'pct_qty' => 10.5, 'pct_item' => 20.0],
            ['time' => '2026-03-06 10:00:00', 'pct_qty' => 10.5, 'pct_item' => 20.0],
            ['time' => '2026-03-06 13:00:00', 'pct_qty' => 10.5, 'pct_item' => 20.0],
        ];

        $result = FACDBTD001::zeroFutureSlots($series, ['pct_qty', 'pct_item'], '2026-03-06 10:00:00');

        $this->assertSame(10.5, $result[0]['pct_qty']);
        // Slot tepat di jam sekarang sudah tiba
        $this->assertSame(20.0, $result[1]['pct_item']);
        $this->assertSame(0, $result[2]['pct_qty']);
        $this->assertSame(0, $result[2]['pct_item']);
        $this->assertSame([false, false, true], array_column($result, 'future'));
        $this->assertSame('2026-03-06 13:00:00', $result[2]['time']);
    }
}
