<?php

namespace App\Models\FACDBT01;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FACDBT_SIMINVTAKINGDATATAB extends Model
{
    use HasFactory;

    // Mengarahkan ke koneksi MySQL eksternal
    protected $connection = 'INV_TAKING';

    // Nama tabel di database berdasarkan dump SQL
    protected $table = 'SIM_INV_TAKING_DATA_TAB';

    // Composite Primary Key sesuai skema DDL
    protected $primaryKey = [
        'MONTH',
        'YEAR',
        'ID_TAKING',
        'REV_NO',
        'CONTRACT',
        'LOCATION_NO',
        'PART_NO'
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    // Matikan timestamps bawaan Laravel (created_at & updated_at)
    public $timestamps = false;

    // Field kolom yang boleh diisi (mass assignable)[cite: 14]
    protected $fillable = [
        'MONTH',
        'YEAR',
        'ID_TAKING',
        'REV_NO',
        'CONTRACT',
        'LOCATION_NO',
        'LOCATION_NAME',
        'WAREHOUSE',
        'LOCATION_GROUP',
        'ID_SCANN',
        'QTY',
        'TAKING_DATE',
        'PART_NO',
        'DESCRIPTION',
        'DIM_QUALITY',
        'REVISE_DATE',
        'NPK',
        'ROWVERSION',
        'ACTIVE_STATE',
        'LOT_BATCH_NO',
        'UNIT_MEAS',
        'INPUT_UNIT_MEAS_GROUP_ID',
        'UNIT_CODE',
        'CONVERSION_FACTOR',
        'CREATED_BY',
        'CREATED_DATETIME',
        'MODIFIED_BY',
        'MODIFIED_DATETIME',
        'HANDLING_UNIT_ID',
        'HANDLING_UNIT_TYPE',
        'WDR',
        'REASON_REVISION',
        'CONFIRMED',
        'sequence_no',
    ];

    // Casting tipe data[cite: 14]
    protected $casts = [
        'ID_TAKING' => 'integer',
        'QTY' => 'decimal:4',
        'TAKING_DATE' => 'datetime',
        'REVISE_DATE' => 'datetime',
        'ROWVERSION' => 'datetime',
        'CONVERSION_FACTOR' => 'double',
        'CREATED_DATETIME' => 'datetime',
        'MODIFIED_DATETIME' => 'datetime',
        'HANDLING_UNIT_ID' => 'integer',
        'sequence_no' => 'integer',
    ];

    /**
     * Relasi ke model FACDBT_SIMINVTAKINGDATAREVISIONTAB berdasarkan ID_TAKING dan REV_NO
     */
    public function revision()
    {
        return $this->hasMany(
            FACDBT_SIMINVTAKINGDATAREVISIONTAB::class,
            ['ID_TAKING', 'REV_NO'],
            ['ID_TAKING', 'REV_NO']
        );
    }

    /**
     * Relasi ke model FACDBT_MSHLOCATION berdasarkan LOCATION_NO (location_no)
     */
    public function location()
    {
        return $this->belongsTo(FACDBT_MSHLOCATION::class, 'LOCATION_NO', 'location_no');
    }

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
}