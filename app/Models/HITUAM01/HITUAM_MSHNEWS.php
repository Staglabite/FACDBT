<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Auth;

/**
 * @method static \Illuminate\Database\Eloquent\Builder filtered($request)
 */
class HITUAM_MSHNEWS extends BaseModel
{
    protected $connection = 'hituam';

    protected $table = 'HITUAM_MSHNEWS';

    protected $fillable = [
        'VTITLE',
        'VSUBJECT',
        'AVIEWERS',
        'ITOTALVIEW',
        'VCONTENT',
        'VIMAGE_PATH',
        'VFILE_PATH',
        'BSTATUS',
        'DPUBLISHED_AT',
        'VCREA',
        'DCREA',
        'VMODI',
        'DMODI',
        'VDELETE',
        'DDELETE'
    ];

    protected $casts = [
        'AVIEWERS' => 'array',
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
