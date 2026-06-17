<?php

namespace App\Services\HITUAM;

// use App\Models\FACTWM03\FACTWM_LOGLOGIN_HISTORY as LoginHistory;
use App\Models\HITUAM01\HITUAM_LOGLOGINHISTORY as LoginHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LoginHistoryService
{
    public function writeHis($user, $isUserSupplier, $requese)
    {
        try {
            $isInternal = $isUserSupplier === null ? 'Internal' : 'Eksternal';
            LoginHistory::create([
                'VUSERNAME' => $user->VUSERNAME ?? null,
                'VFULLNAME' => $user->VNAME ?? null,
                'VEMAIL' => $user->VEMAIL ?? null,
                'VUSERTYPE' => $isInternal,
                'DLASTLOGIN' => Carbon::now(),
                'VIPADDRESS' => $requese->ip(),
                'VUSERAGENT' => $requese->userAgent(),
                'BISACCEPTPRIVACY' => true
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to write login history: ' . $e->getMessage(), [
                'user' => $user->VUSERNAME ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
