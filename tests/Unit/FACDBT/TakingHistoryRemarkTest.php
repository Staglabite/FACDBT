<?php

namespace Tests\Unit\FACDBT;

use App\Services\FACDBT\TakingHistoryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Remark Match / Var(-) / Var(+) di History Taking (FACDBTD006).
 */
class TakingHistoryRemarkTest extends TestCase
{
    public static function variances(): array
    {
        return [
            'taking sama dengan book'      => [0.0, 'Match'],
            'selisih pembulatan dianggap 0' => [0.00001, 'Match'],
            'taking kurang dari book'      => [-3.0, 'Var(-)'],
            'taking lebih dari book'       => [2.5, 'Var(+)'],
        ];
    }

    #[DataProvider('variances')]
    public function test_remark(float $variance, string $expected): void
    {
        $this->assertSame($expected, TakingHistoryService::remark($variance));
    }
}
