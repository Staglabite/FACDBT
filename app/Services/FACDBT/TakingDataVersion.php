<?php

namespace App\Services\FACDBT;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Versi" data taking periode aktif, dipakai dashboard FACDBT04 untuk tahu kapan ada data baru.
 *
 * Versi berubah bila ada scan baru, scan diubah/dihapus (jumlah & total qty berubah), periode baru,
 * book baru, revisi data taking baru, atau event / daftar part event (Master Event) berubah. Nilainya disimpan VERSION_TTL detik, sehingga dashboard
 * paling cepat dihitung ulang tiap VERSION_TTL detik walaupun scan masuk terus-menerus.
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

        $period = RunningTakingPeriod::find($db);

        if (!$period) {
            return 'none';
        }

        // Sama dengan dashboard: CLOSE_DATE kosong (taking belum selesai) = sampai hari ini
        $range = [
            Carbon::parse($period->TAKING_DATE)->startOfDay()->format('Y-m-d H:i:s'),
            Carbon::parse($period->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s'),
        ];

        $taking = $db->table('SIM_INV_TAKING_DATA_TAB')
            ->whereBetween('CREATED_DATETIME', $range)
            ->selectRaw('COUNT(*) as n, MAX(CREATED_DATETIME) as last_scan, SUM(QTY) as qty')
            ->first();

        $book = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', $range)
            ->selectRaw('COUNT(*) as n, MAX(CAST(INV_LIST_NO AS UNSIGNED)) as inv_list_no')
            ->first();

        // Revisi data taking (dashboard Taking Data Revision)
        $revision = $db->table('SIM_INV_TAKING_DATA_REVISION_TAB')
            ->whereBetween('CREATED_DATETIME', $range)
            ->selectRaw('COUNT(*) as n, MAX(MODIFIED_DATETIME) as last_modified')
            ->first();

        return substr(md5(implode('|', [
            $range[0], $range[1],
            $taking->n ?? 0, $taking->last_scan ?? '', $taking->qty ?? 0,
            $book->n ?? 0, $book->inv_list_no ?? '',
            $revision->n ?? 0, $revision->last_modified ?? '',
            TakingEvent::signature($db, $period),
        ])), 0, 12);
    }
}
