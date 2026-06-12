<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use App\Models\HITUAM01\HITUAM_MSHSERVICE as Service;
use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;

/**
 * @method static \Illuminate\Database\Eloquent\Builder filtered($request)
 */

class HITUAM_MSHROLE extends BaseModel
{
    protected $connection = 'hituam';

    protected $table = 'HITUAM_MSHROLES';

    protected $primaryKey = 'NID';

    protected $fillable = [
        'VROLENAME',
        'VROLEDESC',
        'BSTATUS',
    ];

    protected $casts = [
        'BSTATUS' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'HITUAM_MSHUSER', 'IROLE_ID', 'IUSER_ID');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'HITUAM_MSHROLESERVICES', 'VROLE', 'VSERVICE', 'VROLENAME', 'VNAME')->wherePivot('DDELETE', null);
    }

    public function accesses(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'HITUAM_MSHROLEACCESS', 'VROLE', 'VMENUID', 'VROLENAME', 'VAPPID')->wherePivot('BSTATUS', true);
    }

    #[Scope]
    protected function filtered(Builder $query, $request)
    {
        $search = $request->search;

        return $query->when($search, function ($q) use ($search) {
            $q->where('VROLENAME', 'ilike', "%{$search}%");
        });
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
