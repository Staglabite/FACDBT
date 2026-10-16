<?php

namespace App\Models\FACDBT04;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * History hasil taking per (location_no, part_no, LOT_BATCH_NO) per periode taking.
 * Diisi oleh App\Services\FACDBT\TakingHistoryService saat periode taking ditutup.
 */
class FACDBT_SIMINVTAKINGDATAHISTORY extends Model
{
    protected $connection = 'INV_TAKING';
    protected $table = 'SIM_INV_TAKING_DATA_HISTORY';

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = ['IID'];

    protected $casts = [
        'IID' => 'integer',
        'TAKING_DATE' => 'date:Y-m-d',
        'CLOSE_DATE' => 'date:Y-m-d',
        'MONTH' => 'integer',
        'YEAR' => 'integer',
        'time' => 'datetime:Y-m-d H:i:s',
        'qty_book' => 'float',
        'qty_taking' => 'float',
        'qty_variance' => 'float',
        'mat_cost' => 'float',
        'lab_cost' => 'float',
        'foh_cost' => 'float',
        'amount_book' => 'float',
        'amount_taking' => 'float',
        'amount_variance' => 'float',
        'item_book' => 'integer',
        'item_taking' => 'integer',
        'CREATED_DATETIME' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Filter periode Year -> Month -> Event (TAKING_DATE). Nilai kosong = tidak difilter.
     */
    public function scopeForPeriod(Builder $query, $year = null, $month = null, $takingDate = null): Builder
    {
        return $query
            ->when($year, fn ($q) => $q->where('YEAR', (int) $year))
            ->when($month, fn ($q) => $q->where('MONTH', (int) $month))
            ->when($takingDate, fn ($q) => $q->whereDate('TAKING_DATE', $takingDate));
    }
}
