<?php

namespace App\Models\FACDBT01;

use App\Services\FACDBT\TakingEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Master Event (FACDBTF006): jenis taking per periode (TAKING_DATE di SIM_INV_TAKING_CONCLUDING_TAB).
 */
class FACDBT_SIMINVTAKINGEVENT extends Model
{
    protected $connection = 'INV_TAKING';
    protected $table = TakingEvent::TABLE;

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'TAKING_DATE',
        'EVENT_TYPE',
        'DESCRIPTION',
        'CREATED_BY',
        'CREATED_DATETIME',
        'MODIFIED_BY',
        'MODIFIED_DATETIME',
    ];

    protected $casts = [
        'IID' => 'integer',
        'TAKING_DATE' => 'date:Y-m-d',
    ];

    /**
     * Daftar part Pre-Taking.
     */
    public function parts()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGEVENTPART::class, 'EVENT_ID', 'IID');
    }
}
