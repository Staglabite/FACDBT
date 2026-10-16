<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache hasil hitung dashboard FACDBT04.
 *
 * - Disimpan di store khusus (config cache.dashboard_store, default "file") karena datanya besar.
 * - Stale-while-revalidate: selama CACHE_TTL detik data dianggap segar; setelah itu sampai
 *   CACHE_TTL x 5 detik data lama langsung dikirim dan perhitungan ulang dikerjakan setelah
 *   respons terkirim, sehingga pengguna tidak menunggu query berat.
 *
 * Class pemakai wajib punya konstanta CACHE_TTL.
 */
trait CachesDashboard
{
    private function dashboardCache(string $key, Closure $callback): mixed
    {
        return Cache::store(config('cache.dashboard_store', 'file'))
            ->flexible($key, [self::CACHE_TTL, self::CACHE_TTL * 5], $callback);
    }
}
