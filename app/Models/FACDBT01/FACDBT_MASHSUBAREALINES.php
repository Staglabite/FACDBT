<?php

namespace App\Models\FACDBT01;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PpcsoiMshsubarealines extends BaseModel
{
    use HasFactory;

    // Mengarahkan ke koneksi MySQL eksternal
    protected $connection = 'INV_TAKING';

    // Nama tabel di database
    protected $table = 'PPCSOI_MSHSUBAREALINES';

    // Primary Key adalah 'IID' bertipe integer auto increment
    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    // Matikan timestamps bawaan Laravel karena menggunakan custom kolom tanggal
    public $timestamps = false;

    // Kolom yang boleh diisi sesuai struktur DDL
    protected $fillable = [
        'VAREACODE',
        'VSUBAREACODE',
        'VLOCNO',
        'VLOCDESC',
        'VLOCGROUP',
        'VLOCGROUPDESC',
        'VWAREHOUSEID',
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
     * Relasi ke model PpcsoiMshareas (Tabel Area)
     * Mengikat VAREACODE dengan VAREACODE di tabel Area.
     */
    public function area()
    {
        return $this->belongsTo(PpcsoiMshareas::class, 'VAREACODE', 'VAREACODE');
    }

    /**
     * Relasi ke model PpcsoiMshsubareas (Tabel Sub Area)
     * Mengikat VSUBAREACODE dengan VSUBAREACODE di tabel Sub Area.
     */
    public function subArea()
    {
        return $this->belongsTo(PpcsoiMshsubareas::class, 'VSUBAREACODE', 'VSUBAREACODE');
    }

    /**
     * Relasi ke model SimInvLocationTest (Tabel Location Utama)
     * Mengikat VLOCNO di tabel ini dengan location_no di tabel SIM_INV_LOCATION_TEST.
     */
    /**
     * Relasi balik ke model FACDBT_MSHLOCATION
     */
    public function location()
    {
        return $this->belongsTo(FACDBT_MSHLOCATION::class, 'location_no', 'location_no');
    }
}