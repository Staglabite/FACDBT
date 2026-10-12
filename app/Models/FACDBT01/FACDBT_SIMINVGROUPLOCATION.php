<?php

namespace App\Models\FACDBT01;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FACDBT_SIMINVGROUPLOCATION extends Model
{
    use HasFactory;

    protected $connection = 'INV_TAKING';
    protected $table = 'SIM_INV_GROUP_LOCATION';

    protected $primaryKey = 'IID';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'LOCATION_NO',
        'ID_LOC',
        'location_group',
    ];

    protected $casts = [
        'IID' => 'integer',
        'LOCATION_NO' => 'string',
        'ID_LOC' => 'string',
        'location_group' => 'string',
    ];

    /**
     * Relasi ke model FACDBT_MSHLOCATION
     * Mengikat LOCATION_NO di tabel ini dengan location_no di tabel Location.
     */
    public function location()
    {
        return $this->belongsTo(FACDBT_MSHLOCATION::class, 'LOCATION_NO', 'location_no');
    }
}
