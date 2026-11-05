<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use App\Models\HITUAM01\HITUAM_MSHSERVICE as Service;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @method static \Illuminate\Database\Eloquent\Builder filtered($request)
 */

class HITUAM_MSHMENU extends BaseModel
{
    protected $connection = 'hituam';

    protected $table = 'HITUAM_MSHMENUS';

    public $timestamps = false;

    protected $fillable = [
        'VAPPID',
        'VFLAG',
        'VICON',
        'VAPPDESC',
        'VURL',
        'VDESC',
        'VENVAPP',
        'VTYPEAPP',
        'NSORTAPP',
        'VPARENT',
        'NSORTPROJECT',
    ];

    protected $casts = [
        'VFLAG' => \App\Enums\MenuFlag::class,
    ];

    // Relations
    public function child(): HasMany
    {
        return $this->hasMany($this, 'VPARENT', 'VAPPID');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo($this, 'VPARENT', 'VAPPID');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'NSORTPROJECT', 'IID');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'VMENUID', 'VAPPID');
    }

    public function accesses(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'HITUAM_MSHROLEACCESS', 'VMENUID', 'VROLE', 'VAPPID', 'VROLENAME')->wherePivot('BSTATUS', true);
    }

    // Scopes
    #[Scope]
    public function tree(Builder $query, array $allowedIds)
    {
        return $query->with(['child' => function ($child) use ($allowedIds) {
            $child->whereIn('IID', $allowedIds)
                ->orderBy('NSORTAPP');
        }])
            ->whereIn('IID', $allowedIds)
            ->whereNull('NSORTPROJECT')
            ->orderBy('NSORTAPP');
    }

    #[Scope]
    protected function filtered(Builder $query, Request $request)
    {
        $search = $request->search;
        $applicationId = $request->application_id;

        return $query->when($search, function ($q) use ($search) {
            $q->where('VAPPDESC', 'ilike', "%{$search}%");
        })->when($applicationId, function ($q) use ($applicationId) {
            $q->where('NSORTPROJECT', $applicationId);
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
