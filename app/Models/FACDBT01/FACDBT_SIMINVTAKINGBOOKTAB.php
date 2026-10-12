<?php

namespace App\Models\FACDBT01;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FACDBT_SIMINVTAKINGBOOKTAB extends Model
{
    use HasFactory;

    // Mengarahkan ke koneksi MySQL eksternal
    protected $connection = 'INV_TAKING';

    // Nama tabel di database berdasarkan dump SQL
    protected $table = 'SIM_INV_TAKING_BOOK_TAB';

    // Composite Primary Key sesuai skema DDL
    protected $primaryKey = [
        'INV_LIST_NO',
        'SEQ',
        'MONTH',
        'YEAR',
        'CONTRACT',
        'PART_NO',
        'LOCATION_NO',
        'LOT_BATCH_NO'
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    // Matikan timestamps bawaan Laravel (created_at & updated_at)
    // karena menggunakan kolom custom CREATED_DATETIME dan MODIFIED_DATETIME
    public $timestamps = false;

    // Field kolom yang boleh diisi (mass assignable)
    protected $fillable = [
        'INV_LIST_NO',
        'SEQ',
        'MONTH',
        'YEAR',
        'CONTRACT',
        'PART_NO',
        'LOCATION_NO',
        'LOT_BATCH_NO',
        'QUANTITY',
        'UNIT_COST',
        'UNIT_MEAS',
        'HANDLING_UNIT_ID',
        'HANDLING_UNIT_TYPE_ID',
        'ROWVERSION',
        'NPK',
        'CREATED_DATETIME',
        'MODIFIED_DATETIME',
        'CREATED_BY',
        'MODIFIED_BY',
        'VERIFIED',
        'WDR',
        'WAREHOUSE',
        'BAY_NO',
        'ROW_NO',
        'TIER_NO',
        'BIN_NO',
        'AVAILABILITY_CONTROL_ID',
        'AVAILABILITY_CONTROL_DESC',
    ];

    // Casting tipe data
    protected $casts = [
        'SEQ' => 'integer',
        'QUANTITY' => 'double',
        'UNIT_COST' => 'double',
        'HANDLING_UNIT_ID' => 'integer',
        'ROWVERSION' => 'datetime',
        'CREATED_DATETIME' => 'datetime',
        'MODIFIED_DATETIME' => 'datetime',
    ];

    /**
     * Relasi ke model FACDBT_SIMINVTAKINGCONCLUDINGTAB berdasarkan MONTH dan YEAR
     */
    public function concluding()
    {
        return $this->belongsTo(
            FACDBT_SIMINVTAKINGCONCLUDINGTAB::class,
            ['MONTH', 'YEAR'],
            ['MONTH', 'YEAR']
        );
    }

    /**
     * Relasi ke model FACDBT_MSHPART berdasarkan PART_NO (part_no)
     */
    public function part()
    {
        return $this->belongsTo(FACDBT_MSHPART::class, 'PART_NO', 'part_no');
    }

    /**
     * Relasi ke model FACDBT_MSHLOCATION berdasarkan LOCATION_NO (location_no)
     */
    public function location()
    {
        return $this->belongsTo(FACDBT_MSHLOCATION::class, 'LOCATION_NO', 'location_no');
    }
}