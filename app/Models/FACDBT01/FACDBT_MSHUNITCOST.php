<?php

namespace App\Models\FACDBT01;

use App\Models\FACDBT01\Concerns\HasAuditColumns;
use App\Services\FACDBT\FacdbtTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Master Unit Cost (FACDBTF003). Unit cost dashboard = MMATCOST + MLABCOST + MFOHCOST; VUOM 'ml' = part oil.
 */
class FACDBT_MSHUNITCOST extends Model
{
    use HasFactory;
    use HasAuditColumns;

    protected $connection = 'INV_TAKING';
    protected $table = FacdbtTable::UNIT_COSTS;

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'VPARTNO',
        'VPARTDESC',
        'VUOM',
        'VACCGROUP',
        'NONHANDQTY',
        'MMATCOST',
        'MLABCOST',
        'MFOHCOST',
    ];

    protected $casts = [
        'IID' => 'integer',
        'MMATCOST' => 'decimal:2',
        'MLABCOST' => 'decimal:2',
        'MFOHCOST' => 'decimal:2',
        'DCREA' => 'datetime:Y-m-d H:i:s',
        'DMODI' => 'datetime:Y-m-d H:i:s',
        'DDELETE' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Relasi balik ke model FACDBT_MSHPART
     * Unit Cost dimiliki oleh 1 Part berdasarkan Part No.
     */
    public function part()
    {
        return $this->belongsTo(FACDBT_MSHPART::class, 'VPARTNO', 'VPARTNO');
    }
}
