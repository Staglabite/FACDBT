<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Auth;

class HITUAM_MSHCONFIGURATION extends BaseModel
{
    protected $connection = 'hituam';
    
    protected $table = 'HITUAM_MSHCONFIGURATIONS';

    protected $fillable = [
        'VVARIABLE',
        'VVALUE'
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
