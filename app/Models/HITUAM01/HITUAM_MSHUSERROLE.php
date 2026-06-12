<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;

class HITUAM_MSHUSERROLE extends BaseModel
{
    protected $connection = 'hituam';

    protected $table = 'HITUAM_MSHUSERROLES';

    protected $fillable = [
        'VUSERNAME',
        'VROLE',
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
