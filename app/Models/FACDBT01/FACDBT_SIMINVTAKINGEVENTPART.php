<?php

namespace App\Models\FACDBT01;

use App\Services\FACDBT\TakingEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Part yang dihitung dashboard saat event Pre-Taking (Master Event, FACDBTF006).
 */
class FACDBT_SIMINVTAKINGEVENTPART extends Model
{
    protected $connection = 'INV_TAKING';
    protected $table = TakingEvent::PART_TABLE;

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'EVENT_ID',
        'PART_NO',
    ];

    protected $casts = [
        'IID' => 'integer',
        'EVENT_ID' => 'integer',
    ];
}
