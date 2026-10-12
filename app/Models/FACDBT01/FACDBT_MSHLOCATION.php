<?php

namespace App\Models\FACDBT01;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FACDBT_MSHLOCATION extends Model
{
    use HasFactory;

    protected $connection = 'INV_TAKING';
    protected $table = 'SIM_INV_LOCATION_TEST';

    protected $primaryKey = 'location_no';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'location_no',
        'location_group',
        'site',
        'warehouse',
        'location_description',
        'location_group_description',
    ];

    protected $casts = [
        'location_no' => 'string',
        'location_group' => 'string',
        'site' => 'string',
        'warehouse' => 'string',
        'location_description' => 'string',
        'location_group_description' => 'string',
    ];

/**
     * Alias atribut 'id' untuk menyesuaikan dengan trait Datatable bawaan
     */
    public function getIdAttribute()
    {
        return $this->location_no;
    }

    /**
     * Relasi ke model FACDBT_MASHSUBAREALINES
     */
    public function lines()
    {
        return $this->hasMany(FACDBT_MASHSUBAREALINES::class, 'VLOCNO', 'location_no');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGBOOKTAB (One to Many)
     * 1 Location bisa digunakan di banyak transaksi Book Tab berdasarkan location_no.
     */
    public function books()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGBOOKTAB::class, 'LOCATION_NO', 'location_no');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATAREVISIONTAB (One to Many)
     */
    public function revisions()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGDATAREVISIONTAB::class, 'LOCATION_NO', 'location_no');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATATAB (One to Many)
     */
    public function takingData()
    {
        return $this->hasMany(FACDBT_SIMINVTAKINGDATATAB::class, 'LOCATION_NO', 'location_no');
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVGROUPLOCATION (One to Many)
     * 1 Location bisa terdaftar di banyak Group Location berdasarkan location_no.
     */
    public function groupLocations()
    {
        return $this->hasMany(FACDBT_SIMINVGROUPLOCATION::class, 'LOCATION_NO', 'location_no');
    }
}