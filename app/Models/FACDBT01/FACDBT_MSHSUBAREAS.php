<?php

namespace App\Models\FACDBT01;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FACDBT_MSHSUBAREAS extends BaseModel
{
    use HasFactory;

    // Mengarahkan ke koneksi MySQL eksternal
    protected $connection = 'INV_TAKING';

    // Nama tabel di database berdasarkan dump SQL
    protected $table = 'PPCSOI_MSHSUBAREAS';

    // Primary Key adalah 'IID' bertipe integer auto increment
    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    // Matikan timestamps bawaan Laravel karena menggunakan custom kolom DCREA, DMODI, DDELETE
    public $timestamps = false;

    // Kolom yang boleh diisi sesuai struktur DDL
    protected $fillable = [
        'VAREACODE',
        'VSUBAREACODE',
        'VSUBAREANAME',
        'ISTATUS',
        'VCREA',
        'DCREA',
        'VMODI',
        'DMODI',
        'VDELETE',
        'DDELETE',
    ];

    // Casting tipe data
    protected $casts = [
        'IID' => 'integer',
        'ISTATUS' => 'integer',
        'DCREA' => 'datetime',
        'DMODI' => 'datetime',
        'DDELETE' => 'datetime',
    ];

    /**
     * Relasi ke model FACDBT_MSHAREAS (Many to One)
     * Karena 1 Area memiliki banyak Sub Area, maka Sub Area belongsTo Area.
     * Menggunakan 'VAREACODE' sebagai foreign key dan local key.
     */
    public function area()
    {
        return $this->belongsTo(FACDBT_MSHAREAS::class, 'VAREACODE', 'VAREACODE');
    }

    /**
     * 1 Sub Area memiliki banyak Lines (Lokasi).
     */
    public function lines()
    {
        return $this->hasMany(FACDBT_MASHSUBAREALINES::class, 'VSUBAREACODE', 'VSUBAREACODE');
    }
}