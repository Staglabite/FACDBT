<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use App\Services\FACDBT\RunningTakingPeriod;
use App\Services\FACDBT\TakingDataVersion;
use App\Services\FACDBT\TakingEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Versi data taking untuk dashboard FACDBT04: browser memuat ulang chart hanya bila versinya berubah.
// Ikut dikirim event periode berjalan (Master Event): namanya ditampilkan di baris status dashboard dan
// kodenya dipakai Main Dashboard untuk berpindah ke / dari tampilan TWH.
class DashboardDataVersion extends Controller
{
    public function __invoke()
    {
        $event = self::runningEvent();

        return response()->json([
            'version'    => TakingDataVersion::current(),
            'event'      => $event['name'],
            'event_type' => $event['type'],
        ]);
    }

    /**
     * Event periode berjalan, disimpan TakingDataVersion::VERSION_TTL detik. Dipakai juga oleh Main Dashboard
     * saat memilih tampilan, supaya halaman dan pengecekan berkala selalu melihat event yang sama.
     *
     * @return array{type: ?string, name: ?string}
     */
    public static function runningEvent(): array
    {
        return Cache::store(config('cache.dashboard_store', 'file'))
            ->remember('FACDBT04:running-event', TakingDataVersion::VERSION_TTL, function () {
                $db    = DB::connection('INV_TAKING');
                $event = TakingEvent::forPeriod($db, RunningTakingPeriod::find($db));

                return ['type' => $event?->type, 'name' => $event?->name()];
            });
    }
}
