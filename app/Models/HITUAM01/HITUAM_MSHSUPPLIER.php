<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use App\Models\FACTWM01\FACTWM_MSHSUPPLIER_COMMUNICATION_METHOD as SupplierMethod;
use App\Models\HITUAM01\HITUAM_MSHSUPPLIER_COMMUNICATION_METHOD as SupplierMethod;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * @method static \Illuminate\Database\Eloquent\Builder filtered($request)
 */
class HITUAM_MSHSUPPLIER extends BaseModel
{
    protected $table = 'HITUAM_MSHSUPPLIERS';

    protected $fillable = [
        'VSUPPLIER_CODE',
        'VNAME',
        'VADDRESS',
        'VCOUNTRY',
        'VNIK',
        'VNPWP',
        'BPKP',
        'VPAYMENT_TERM',
        'VGROUP',
        'VSTAT_GROUP',
        'VTAX_CODE'
    ];

    public function methods(): HasMany
    {
        return $this->hasMany(SupplierMethod::class, 'ISUPPLIER_ID', 'IID');
    }

    #[Scope]
    protected function filtered(Builder $query, $request)
    {
        $search = $request->search;

        return $query->when($search, function ($q) use ($search) {
            $q->whereAny(['VSUPPLIER_CODE', 'VNAME'], 'ilike', "%$search%");
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
