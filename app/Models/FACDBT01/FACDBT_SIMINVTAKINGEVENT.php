<?php

namespace App\Models\FACDBT01;

use App\Models\FACDBT01\Concerns\HasAuditColumns;
use App\Services\FACDBT\TakingEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Master Event (FACDBTF006): jenis taking per periode (DTAKINGDATE = TAKING_DATE di SIM_INV_TAKING_CONCLUDING_TAB).
 */
class FACDBT_SIMINVTAKINGEVENT extends Model
{
    use HasAuditColumns;

    protected $connection = 'INV_TAKING';
    protected $table = TakingEvent::TABLE;

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'DTAKINGDATE',
        'VEVENTTYPE',
        'VDESC',
    ];

    protected $casts = [
        'IID' => 'integer',
        'DTAKINGDATE' => 'date:Y-m-d',
        'DCREA' => 'datetime:Y-m-d H:i:s',
        'DMODI' => 'datetime:Y-m-d H:i:s',
        'DDELETE' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Daftar part Pre-Taking (yang belum dihapus).
     */
    public function parts()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGEVENTPART::class, 'IEVENTID', 'IID');
    }
}
