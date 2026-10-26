<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns;

use App\Services\FACDBT\RunningTakingPeriod;
use App\Services\FACDBT\TakingEvent;
use Illuminate\Database\Query\Builder;

/**
 * Event (Master Event) periode taking yang sedang berjalan: query book / taking / revisi dashboard dibatasi
 * ke data event tersebut (lihat TakingEvent::scope()).
 */
trait UsesTakingEvent
{
    private function takingEvent($db): ?TakingEvent
    {
        return once(fn () => TakingEvent::forPeriod($db, RunningTakingPeriod::find($db)));
    }

    private function eventScope(Builder $query, $db, string $locationColumn = 'LOCATION_NO', string $partColumn = 'PART_NO'): Builder
    {
        return TakingEvent::scope($query, $this->takingEvent($db), $locationColumn, $partColumn);
    }
}
