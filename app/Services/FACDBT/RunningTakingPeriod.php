<?php

namespace App\Services\FACDBT;

use Illuminate\Database\Connection;

/**
 * Periode taking yang sedang berjalan, dipakai semua dashboard FACDBT04.
 *
 * Periode = baris terbaru (TAKING_DATE) di SIM_INV_TAKING_CONCLUDING_TAB. Begitu periode itu ditutup
 * (ROWSTATE berubah dari Started menjadi Closed) tidak ada periode berjalan, sehingga dashboard kosong
 * sampai periode taking berikutnya dibuat. Hasil periode yang sudah ditutup ada di History Taking.
 */
class RunningTakingPeriod
{
    public const CLOSED_ROWSTATE = 'Closed';

    /**
     * @return object{MONTH: mixed, YEAR: mixed, TAKING_DATE: string, CLOSE_DATE: string, ROWSTATE: ?string}|null
     */
    public static function find(Connection $db): ?object
    {
        $period = $db->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('MONTH', 'YEAR', 'TAKING_DATE', 'CLOSE_DATE', 'ROWSTATE')
            ->orderByDesc('TAKING_DATE')
            ->first();

        if (!$period || strcasecmp(trim((string) $period->ROWSTATE), self::CLOSED_ROWSTATE) === 0) {
            return null;
        }

        return $period;
    }
}
