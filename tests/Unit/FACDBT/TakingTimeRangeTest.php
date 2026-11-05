<?php

namespace Tests\Unit\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\FiltersTakingRange;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Filter rentang waktu taking (Dari - Sampai) di dashboard FACDBT04.
 */
class TakingTimeRangeTest extends TestCase
{
    private const TAKING = '2026-03-06 00:00:00';
    private const CLOSE  = '2026-03-07 23:59:59';

    private function filter(): object
    {
        return new class {
            use FiltersTakingRange;

            public function days(string $taking, string $close): array
            {
                return $this->timeRangePayload($taking, $close)['days'];
            }

            public function range(array $query, string $taking, string $close): array
            {
                return $this->takingRange(new Request($query), $taking, $close);
            }
        };
    }

    public function test_taking_satu_hari_hanya_punya_satu_pilihan_hari(): void
    {
        $this->assertSame(
            [['value' => '2026-03-06', 'label' => 'Hari 1 (6 Mar)']],
            $this->filter()->days(self::TAKING, '2026-03-06 23:59:59')
        );
    }

    public function test_taking_dua_hari_punya_hari_1_dan_hari_2(): void
    {
        $this->assertSame(
            ['Hari 1 (6 Mar)', 'Hari 2 (7 Mar)'],
            array_column($this->filter()->days(self::TAKING, self::CLOSE), 'label')
        );
    }

    public static function ranges(): array
    {
        return [
            'tanpa filter = seluruh periode'        => [[], [self::TAKING, self::CLOSE]],
            'sampai mencakup seluruh menitnya'      => [['from' => '2026-03-06 13:00', 'to' => '2026-03-06 16:00'], ['2026-03-06 13:00:00', '2026-03-06 16:00:59']],
            'lintas hari'                            => [['from' => '2026-03-06 19:30', 'to' => '2026-03-07 09:00'], ['2026-03-06 19:30:00', '2026-03-07 09:00:59']],
            'dari > sampai ditukar'                  => [['from' => '2026-03-07 09:00', 'to' => '2026-03-06 13:00'], ['2026-03-06 13:00:00', '2026-03-07 09:00:59']],
            'di luar periode dibatasi'               => [['from' => '2026-03-01 09:00', 'to' => '2026-03-09 13:00'], [self::TAKING, self::CLOSE]],
            'nilai tidak valid diabaikan'            => [['from' => 'bukan-jam', 'to' => '2026-03-06 08:00'], [self::TAKING, '2026-03-06 08:00:59']],
        ];
    }

    #[DataProvider('ranges')]
    public function test_rentang_waktu(array $query, array $expected): void
    {
        $this->assertSame($expected, $this->filter()->range($query, self::TAKING, self::CLOSE));
    }
}
