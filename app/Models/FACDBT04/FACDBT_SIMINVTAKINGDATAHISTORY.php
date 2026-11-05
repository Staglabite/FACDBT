<?php

namespace App\Models\FACDBT04;

use App\Models\FACDBT01\Concerns\HasAuditColumns;
use App\Services\FACDBT\FacdbtTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * History hasil taking per (Location No, Part No, Lot Batch No) per periode taking (FACDBT_HISTAKINGDATAS).
 * Diisi oleh App\Services\FACDBT\TakingHistoryService saat periode taking ditutup.
 */
class FACDBT_SIMINVTAKINGDATAHISTORY extends Model
{
    use HasAuditColumns;

    protected $connection = 'FACDBT';
    protected $table = FacdbtTable::HISTORY;

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = ['IID'];

    protected $casts = [
        'IID' => 'integer',
        'DTAKINGDATE' => 'date:Y-m-d',
        'DCLOSEDATE' => 'date:Y-m-d',
        'IMONTH' => 'integer',
        'IYEAR' => 'integer',
        'DTAKINGTIME' => 'datetime:Y-m-d H:i:s',
        'NQTYBOOK' => 'float',
        'NQTYTAKING' => 'float',
        'NQTYVARIANCE' => 'float',
        'MMATCOST' => 'float',
        'MLABCOST' => 'float',
        'MFOHCOST' => 'float',
        'MAMOUNTBOOK' => 'float',
        'MAMOUNTTAKING' => 'float',
        'MAMOUNTVARIANCE' => 'float',
        'DCREA' => 'datetime:Y-m-d H:i:s',
        'DMODI' => 'datetime:Y-m-d H:i:s',
        'DDELETE' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Filter periode Year -> Month -> Taking (DTAKINGDATE). Nilai kosong = tidak difilter.
     */
    public function scopeForPeriod(Builder $query, $year = null, $month = null, $takingDate = null): Builder
    {
        return $query
            ->when($year, fn ($q) => $q->where('IYEAR', (int) $year))
            ->when($month, fn ($q) => $q->where('IMONTH', (int) $month))
            ->when($takingDate, fn ($q) => $q->whereDate('DTAKINGDATE', $takingDate));
    }

    /**
     * Filter jenis event (Master Event, kolom VEVENTTYPE). Kosong = tidak difilter.
     */
    public function scopeForEvent(Builder $query, $eventType = null): Builder
    {
        $eventType = strtoupper(trim((string) $eventType));

        return $query->when($eventType !== '', fn ($q) => $q->where('VEVENTTYPE', $eventType));
    }
}
