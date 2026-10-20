<?php

namespace App\Services\FACDBT;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Versi" data taking periode aktif, dipakai dashboard FACDBT04 untuk tahu kapan ada data baru.
 *
 * Versi berubah bila ada scan baru, scan diubah/dihapus (jumlah & total qty berubah), periode baru,
 * atau book baru. Nilainya disimpan VERSION_TTL detik, sehingga dashboard paling cepat dihitung ulang
 * tiap VERSION_TTL detik walaupun scan masuk terus-menerus.
 */
class TakingDataVersion
{
    private const CONN = 'INV_TAKING';

    // Jarak minimal antar perhitungan ulang dashboard (detik)
    public const VERSION_TTL = 5;

    public static function current(): string
    {
        return Cache::store(config('cache.dashboard_store', 'file'))
            ->remember('FACDBT04:data-version', self::VERSION_TTL, fn () => self::compute());
    }

    private static function compute(): string
    {
        $db = DB::connection(self::CONN);

        $period = $db->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('TAKING_DATE', 'CLOSE_DATE')
            ->orderByDesc('TAKING_DATE')
            ->first();

        if (!$period) {
            return 'none';
        }

        $range = [
            date('Y-m-d 00:00:00', strtotime((string) $period->TAKING_DATE)),
            date('Y-m-d 23:59:59', strtotime((string) $period->CLOSE_DATE)),
        ];

        $taking = $db->table('SIM_INV_TAKING_DATA_TAB')
            ->whereBetween('CREATED_DATETIME', $range)
            ->selectRaw('COUNT(*) as n, MAX(CREATED_DATETIME) as last_scan, SUM(QTY) as qty')
            ->first();

        $book = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', $range)
            ->selectRaw('COUNT(*) as n, MAX(CAST(INV_LIST_NO AS UNSIGNED)) as inv_list_no')
            ->first();

        return substr(md5(implode('|', [
            $range[0], $range[1],
            $taking->n ?? 0, $taking->last_scan ?? '', $taking->qty ?? 0,
            $book->n ?? 0, $book->inv_list_no ?? '',
        ])), 0, 12);
    }
}
