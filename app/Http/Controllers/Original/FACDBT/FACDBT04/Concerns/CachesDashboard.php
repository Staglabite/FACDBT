<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns;

use App\Services\FACDBT\TakingDataVersion;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Cache hasil hitung dashboard FACDBT04.
 *
 * - Disimpan di store khusus (config cache.dashboard_store, default "file") karena datanya besar.
 * - Key cache memuat versi data taking (TakingDataVersion), jadi begitu ada scan baru hasil dihitung
 *   ulang tanpa menunggu cache kedaluwarsa. Tanpa scan baru, hasil disimpan CACHE_TTL detik
 *   (supaya perubahan master seperti unit cost / area tetap ikut terbaca).
 * - Satu perhitungan untuk semua pengguna: saat banyak browser meminta data yang sama bersamaan,
 *   hanya satu yang menghitung, lainnya menunggu lalu memakai hasilnya.
 *
 * Class pemakai wajib punya konstanta CACHE_TTL.
 */
trait CachesDashboard
{
    private function dashboardCache(string $key, Closure $callback): mixed
    {
        $store = Cache::store(config('cache.dashboard_store', 'file'));
        $key .= ':' . TakingDataVersion::current();

        $cached = $store->get($key);
        if ($cached !== null) {
            return $cached;
        }

        try {
            return $store->lock($key . ':lock', 120)
                ->block(60, fn () => $store->remember($key, self::CACHE_TTL, $callback));
        } catch (LockTimeoutException $e) {
            // Perhitungan lain belum selesai setelah 60 detik: hitung sendiri daripada gagal
            return $store->remember($key, self::CACHE_TTL, $callback);
        }
    }
}
