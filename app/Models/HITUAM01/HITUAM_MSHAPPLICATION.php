<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class HITUAM_MSHAPPLICATION extends BaseModel
{
    protected $connection = 'hituam';

    protected $table = 'HITUAM_MSHAPPLICATION';

    protected $fillable = [
        'VPROJECTDESC',
        'VDEPT',
        'VPIC',
        'VPORTALACCESS',
        'VPUBLISH',
        'VPORTALNAME',
        'VOPERATIONAL',
        'VSTRDZATION',
        'VPREFIXPROJECT',
        'VDATABASE',
        'NORDERPROJECT',
        'VICON',
        'BIS_EMBED',
        'VHOST', // SSO target URL for external applications.
    ];

    protected $casts = [
        'BIS_EMBED' => 'boolean',
    ];

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class, 'NSORTPROJECT', 'IID');
    }

    public function isExternal(): bool
    {
        return filled($this->VHOST);
    }

    public function scopeFiltered($query, $request)
    {
        $search = $request->search ?? null;

        return $query->when($search, function ($q) use ($search) {
            $q->where('VPROJECTDESC', 'ilike', "%{$search}%")
              ->orWhere('VDEPT', 'ilike', "%{$search}%");
        });
    }

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
