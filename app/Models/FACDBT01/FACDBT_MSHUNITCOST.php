<?php

namespace App\Models\FACDBT01;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FACDBT_MSHUNITCOST extends Model
{
    use HasFactory;

    // Mengarahkan ke koneksi MySQL eksternal
    protected $connection = 'INV_TAKING';

    // Nama tabel di database berdasarkan dump SQL
    protected $table = 'SIM_INV_UNIT_COST_TEST';

    // Primary Key adalah 'id' bertipe integer auto increment
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    // Tabel ini tidak memiliki timestamps bawaan Laravel
    public $timestamps = false;

    // Field kolom sesuai dump SQL SIM_INV_UNIT_COST_TEST
    protected $fillable = [
        'part_no',
        'inventory_part_description',
        'inventory_uom',
        'accounting_group',
        'on_hand_qty',
        'mat_cost',
        'lab_cost',
        'foh_cost',
    ];

    // Casting tipe data
    protected $casts = [
        'id' => 'integer',
        'part_no' => 'string',
        'inventory_part_description' => 'string',
        'inventory_uom' => 'string',
        'accounting_group' => 'string',
        'on_hand_qty' => 'string',
        'mat_cost' => 'decimal:2',
        'lab_cost' => 'decimal:2',
        'foh_cost' => 'decimal:2',
    ];

    /**
     * Relasi balik ke model FACDBT_MSHPART
     * Unit Cost dimiliki oleh 1 Part berdasarkan part_no.
     */
    public function part()
    {
        return $this->belongsTo(FACDBT_MSHPART::class, 'part_no', 'part_no');
    }
}