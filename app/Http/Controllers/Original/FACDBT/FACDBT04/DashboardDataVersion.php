<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use App\Services\FACDBT\TakingDataVersion;

// Versi data taking untuk dashboard FACDBT04: browser memuat ulang chart hanya bila versinya berubah
class DashboardDataVersion extends Controller
{
    public function __invoke()
    {
        return response()->json(['version' => TakingDataVersion::current()]);
    }
}
