<?php

namespace App\Models\FACDBT01;

use App\Models\FACDBT01\Concerns\HasAuditColumns;
use App\Services\FACDBT\FacdbtTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Master Group Location (FACDBTF005): area dashboard (VLOCGROUP) per lokasi.
 */
class FACDBT_SIMINVGROUPLOCATION extends Model
{
    use HasFactory;
    use HasAuditColumns;

    protected $connection = 'FACDBT';
    protected $table = FacdbtTable::GROUP_LOCATIONS;

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'VLOCATIONNO',
        'VIDLOC',
        'VLOCGROUP',
        'VSTREAM',
    ];

    protected $casts = [
        'IID' => 'integer',
        'DCREA' => 'datetime:Y-m-d H:i:s',
        'DMODI' => 'datetime:Y-m-d H:i:s',
        'DDELETE' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Relasi ke model FACDBT_MSHLOCATION berdasarkan Location No.
     */
    public function location()
    {
        return $this->belongsTo(FACDBT_MSHLOCATION::class, 'VLOCATIONNO', 'VLOCATIONNO');
    }
}
