<?php

namespace App\Models\FACDBT01;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FACDBT_MSHPART extends Model
{
    use HasFactory;

    protected $connection = 'INV_TAKING';
    protected $table = 'SIM_INV_PART_TEST';

    protected $primaryKey = 'part_no';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'part_no',
        'inventory_part_description',
        'accounting_group',
        'stream',
        'seg',
        'prod_group',
        'part_group',
    ];

    protected $casts = [
        'part_no' => 'string',
        'inventory_part_description' => 'string',
        'accounting_group' => 'string',
        'stream' => 'string',
        'seg' => 'string',
        'prod_group' => 'string',
        'part_group' => 'string',
    ];

    /**
     * Relasi ke model FACDBT_MSHUNITCOST
     */
    public function unitCost()
    {
        return $this->hasOne(FACDBT_MSHUNITCOST::class, 'part_no', 'part_no');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGBOOKTAB (One to Many)
     * 1 Part bisa berada di banyak transaksi Book Tab berdasarkan part_no.
     */
    public function books()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGBOOKTAB::class, 'PART_NO', 'part_no');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATAREVISIONTAB (One to Many)
     */
    public function revisions()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGDATAREVISIONTAB::class, 'PART_NO', 'part_no');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATATAB (One to Many)
     */
    public function takingData()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGDATATAB::class, 'PART_NO', 'part_no');
    }
}