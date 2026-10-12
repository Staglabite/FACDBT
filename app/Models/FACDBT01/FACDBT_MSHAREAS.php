<?php

namespace App\Models\FACDBT01;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FACDBT_MSHAREAS extends BaseModel
{
    use HasFactory;

    // Mengarahkan ke koneksi MySQL eksternal
    protected $connection = 'INV_TAKING';

    // Nama tabel di database berdasarkan dump SQL
    protected $table = 'PPCSOI_MSHAREAS';

    // Primary Key adalah 'IID' bertipe integer auto increment
    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    // Matikan timestamps bawaan Laravel (created_at & updated_at) 
    // karena menggunakan custom kolom DCREA dan DMODI
    public $timestamps = false;

    // Kolom yang boleh diisi (mass assignable) sesuai struktur DDL
    protected $fillable = [
        'VAREACODE',
        'VAREANAME',
        'ISTATUS',
        'VTAKINGTYPE',
        'VCREA',
        'DCREA',
        'VMODI',
        'DMODI',
    ];

    // Casting tipe data agar dikonversi otomatis oleh Eloquent
    protected $casts = [
        'IID' => 'integer',
        'ISTATUS' => 'integer',
        'DCREA' => 'datetime',
        'DMODI' => 'datetime',
    ];

    /**
     * Relasi ke model FACDBT_MSHSUBAREAS (One to Many)
     * 1 Area memiliki banyak Sub Area.
     */
    public function subAreas()
    {
        return $this->hasMany(FACDBT_MSHSUBAREAS::class, 'VAREACODE', 'VAREACODE');
    }

    /**
     * 1 Area memiliki banyak Lines (Lokasi) secara langsung berdasarkan VAREACODE.
     */
    public function lines()
    {
        return $this->hasMany(FACDBT_MASHSUBAREALINES::class, 'VAREACODE', 'VAREACODE');
    }
}