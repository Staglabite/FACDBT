<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use App\Services\FACDBT\RunningTakingPeriod;
use App\Services\FACDBT\TakingDataVersion;
use App\Services\FACDBT\TakingEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Versi data taking untuk dashboard FACDBT04: browser memuat ulang chart hanya bila versinya berubah.
// Ikut dikirim nama event periode berjalan (Master Event) untuk ditampilkan di baris status dashboard.
class DashboardDataVersion extends Controller
{
    public function __invoke()
    {
        $event = Cache::store(config('cache.dashboard_store', 'file'))
            ->remember('FACDBT04:event-name', TakingDataVersion::VERSION_TTL, function () {
                $db = DB::connection('INV_TAKING');

                return TakingEvent::forPeriod($db, RunningTakingPeriod::find($db))?->name() ?? '';
            });

        return response()->json([
            'version' => TakingDataVersion::current(),
            'event'   => $event !== '' ? $event : null,
        ]);
    }
}
