<?php

namespace App\Models\FACDBT01;

use App\Models\FACDBT01\Concerns\HasAuditColumns;
use App\Services\FACDBT\TakingEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Part yang dihitung dashboard saat event Pre-Taking (Master Event, FACDBTF006).
 */
class FACDBT_SIMINVTAKINGEVENTPART extends Model
{
    use HasAuditColumns;

    protected $connection = 'INV_TAKING';
    protected $table = TakingEvent::PART_TABLE;

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'IEVENTID',
        'VPARTNO',
    ];

    protected $casts = [
        'IID' => 'integer',
        'IEVENTID' => 'integer',
        'DCREA' => 'datetime:Y-m-d H:i:s',
        'DMODI' => 'datetime:Y-m-d H:i:s',
        'DDELETE' => 'datetime:Y-m-d H:i:s',
    ];
}
