<?php

namespace App\Models\FACDBT01;

use App\Models\FACDBT01\Concerns\HasAuditColumns;
use App\Services\FACDBT\FacdbtTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Master Location (FACDBTF001).
 */
class FACDBT_MSHLOCATION extends Model
{
    use HasFactory;
    use HasAuditColumns;

    protected $connection = 'INV_TAKING';
    protected $table = FacdbtTable::LOCATIONS;

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'VLOCATIONNO',
        'VLOCGROUP',
        'VSITE',
        'VWAREHOUSE',
        'VLOCDESC',
        'VLOCGROUPDESC',
    ];

    protected $casts = [
        'IID' => 'integer',
        'DCREA' => 'datetime:Y-m-d H:i:s',
        'DMODI' => 'datetime:Y-m-d H:i:s',
        'DDELETE' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Relasi ke model FACDBT_MASHSUBAREALINES
     */
    public function lines()
    {
        return $this->hasMany(FACDBT_MASHSUBAREALINES::class, 'VLOCNO', 'VLOCATIONNO');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGBOOKTAB (One to Many)
     * 1 Location bisa digunakan di banyak transaksi Book Tab berdasarkan Location No.
     */
    public function books()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGBOOKTAB::class, 'LOCATION_NO', 'VLOCATIONNO');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATAREVISIONTAB (One to Many)
     */
    public function revisions()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGDATAREVISIONTAB::class, 'LOCATION_NO', 'VLOCATIONNO');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATATAB (One to Many)
     */
    public function takingData()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGDATATAB::class, 'LOCATION_NO', 'VLOCATIONNO');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVGROUPLOCATION (One to Many)
     * 1 Location bisa terdaftar di banyak Group Location berdasarkan Location No.
     */
    public function groupLocations()
    {
        return $this->hasMany(FACDBT_SIMINVGROUPLOCATION::class, 'VLOCATIONNO', 'VLOCATIONNO');
    }
}
