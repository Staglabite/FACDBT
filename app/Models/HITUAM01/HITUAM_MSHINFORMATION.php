<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class HITUAM_MSHINFORMATION extends BaseModel
{
    use SoftDeletes;

    // Sesuaikan dengan nama table di database
    protected $connection = 'hituam';
    protected $table = 'HITUAM_MSHINFORMATION';
    protected $primaryKey = 'IID';
    const CREATED_AT = 'DCREA';

    const UPDATED_AT = 'DMODI';

    const DELETED_AT = 'DDELETE';
    protected $fillable = [
        'VNOTES',
        'DFROM',
        'DTO',
        'VUSER_TYPE',
        'VCATEGORY',
        'VFILE_INFORMATION',
        'VUPDLOAD_DATA_VENDOR',
        'VUPDLOAD_FOTO_ASSET',
        'VCREA',
        'DCREA',
        'VMODI',
        'DMODI',
        'VDELETE',
    ];
    protected $casts = [
        'DFROM' => 'datetime',
        'DTO' => 'datetime',
    ];

        protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Jangan isi DMODI pada create, hanya DCREA saja
            $model->VMODI = null;
            $model->DMODI = null;
        });

        static::updating(function ($model) {
            $user = Auth::user()?->VUSERNAME ?? Auth::user()?->username ?? 'SYSTEM';

            $model->VMODI = $user;
            $model->DMODI = now();
        });
    }
}
