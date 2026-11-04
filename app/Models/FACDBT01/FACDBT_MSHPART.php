<?php

namespace App\Models\FACDBT01;

use App\Models\FACDBT01\Concerns\HasAuditColumns;
use App\Services\FACDBT\FacdbtTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Master Part (FACDBTF002).
 */
class FACDBT_MSHPART extends Model
{
    use HasFactory;
    use HasAuditColumns;

    protected $connection = 'FACDBT';
    protected $table = FacdbtTable::PARTS;

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'VPARTNO',
        'VPARTDESC',
        'VACCGROUP',
        'VSEG',
        'VPRODGROUP',
        'VPARTGROUP',
    ];

    protected $casts = [
        'IID' => 'integer',
        'DCREA' => 'datetime:Y-m-d H:i:s',
        'DMODI' => 'datetime:Y-m-d H:i:s',
        'DDELETE' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Relasi ke model FACDBT_MSHUNITCOST
     */
    public function unitCost()
    {
        return $this->hasOne(FACDBT_MSHUNITCOST::class, 'VPARTNO', 'VPARTNO');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGBOOKTAB (One to Many)
     * 1 Part bisa berada di banyak transaksi Book Tab berdasarkan Part No.
     */
    public function books()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGBOOKTAB::class, 'PART_NO', 'VPARTNO');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATAREVISIONTAB (One to Many)
     */
    public function revisions()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGDATAREVISIONTAB::class, 'PART_NO', 'VPARTNO');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATATAB (One to Many)
     */
    public function takingData()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGDATATAB::class, 'PART_NO', 'VPARTNO');
    }
}
