<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;

class HITUAM_MSHSERVICE extends BaseModel
{
    protected $connection = 'hituam';

    protected $table = 'HITUAM_MSHSERVICES';

    protected $fillable = [
        'VNAME',
        'VDESC',
        'VURL',
        'VMETHOD',
        'DBEGINEFF',
        'DENDEFF',
        'VMENUID',
        'IAPPLICATIONID',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(HITUAM_MSHAPPLICATION::class, 'IAPPLICATIONID', 'IID');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'VMENUID', 'VAPPID');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'HITUAM_MSHROLE_SERVICES', 'ISERVICE_ID', 'IROLE_ID');
    }

    protected static function boot()
    {
        parent::boot();

        // Event ketika creating (insert)
        static::creating(function ($model) {
            $user = Auth::user()?->VUSERNAME ?? Auth::user()?->username ?? Auth::user()?->email ?? 'SYSTEM';

            $model->VCREA = $user;
            $model->DCREA = now();

            // VMODI dan DMODI tidak diisi saat create
            $model->VMODI = null;
            $model->DMODI = null;
        });

        // Event ketika updating
        static::updating(function ($model) {
            $user = Auth::user()?->VUSERNAME ?? Auth::user()?->username ?? Auth::user()?->email ?? 'SYSTEM';

            $model->VMODI = $user;
            $model->DMODI = now();
        });

        // Event ketika deleting (soft delete)
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                $user = Auth::user()?->VUSERNAME ?? Auth::user()?->username ?? Auth::user()?->email ?? 'SYSTEM';

                $model->VDELETE = $user;
                $model->DDELETE = now();
                $model->save();
            }
        });
    }
}
