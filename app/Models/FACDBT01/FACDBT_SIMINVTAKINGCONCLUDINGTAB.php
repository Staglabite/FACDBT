<?php

namespace App\Models\FACDBT01;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FACDBT_SIMINVTAKINGCONCLUDINGTAB extends Model
{
    use HasFactory;

    protected $connection = 'INV_TAKING';
    protected $table = 'SIM_INV_TAKING_CONCLUDING_TAB';

    protected $primaryKey = ['MONTH', 'YEAR', 'TAKING_DATE'];
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'MONTH',
        'YEAR',
        'CLOSE_DATE',
        'NPK',
        'NAME',
        'ROWVERSION',
        'ROWSTATE',
        'TAKING_DATE',
        'DATA_ENTRY',
        'CREATED_DATETIME',
        'CREATED_BY',
        'MODIFIED_DATETIME',
        'MODIFIED_BY',
    ];

    protected $casts = [
        'MONTH' => 'string',
        'YEAR' => 'string',
        'CLOSE_DATE' => 'date',
        'NPK' => 'string',
        'NAME' => 'string',
        'ROWVERSION' => 'datetime',
        'ROWSTATE' => 'string',
        'TAKING_DATE' => 'date',
        'DATA_ENTRY' => 'string',
        'CREATED_DATETIME' => 'datetime',
        'CREATED_BY' => 'string',
        'MODIFIED_DATETIME' => 'datetime',
        'MODIFIED_BY' => 'string',
    ];

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGBOOKTAB (One to Many)
     * 1 Concluding Record memiliki banyak Book Tab Records berdasarkan MONTH dan YEAR.
     */
    public function books()
    {
        return $this->hasMany(
            FACDBT_SIMINVTAKINGBOOKTAB::class,
            ['MONTH', 'YEAR'],
            ['MONTH', 'YEAR']
        );
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATAREVISIONTAB (One to Many)
     */
    public function revisions()
    {
        return $this->hasMany(
            FACDBT_SIMINVTAKINGDATAREVISIONTAB::class,
            ['MONTH', 'YEAR'],
            ['MONTH', 'YEAR']
        );
    }

    /**
     * Relasi balik ke model FACDBT_SIMINVTAKINGDATATAB (One to Many)
     */
    public function takingData()
    {
        return $this->hasMany(
            FACDBT_SIMINVTAKINGDATATAB::class,
            ['MONTH', 'YEAR'],
            ['MONTH', 'YEAR']
        );
    }
}