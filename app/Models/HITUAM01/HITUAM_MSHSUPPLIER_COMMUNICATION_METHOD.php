<?php

namespace App\Models\HITUAM01;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * @method static \Illuminate\Database\Eloquent\Builder filtered($request)
 * @method static \Illuminate\Database\Eloquent\Builder specific($data)
 */
class HITUAM_MSHSUPPLIER_COMMUNICATION_METHOD extends BaseModel
{
    protected $connection = 'hituam';

    protected $table = 'HITUAM_MSHSUPPLIER_COMMUNICATION_METHODS';

    protected $fillable = [
        'ICOMM_ID',
        'VSUPPLIER_CODE',
        'VSUPPLIER_NAME',
        'VNAME',
        'VUSERNAME',
        'VMETHOD_ID',
        'VDESCRIPTION',
        'VADDRESS_ID',
        'VPARTY_TYPE_DB_VAL',
        'BMETHOD_DEFAULT',
        'IUSER_ID',
        'ISUPPLIER_ID'
    ];

    #[Scope]
    protected function filtered(Builder $query, $request, $method)
    {
        $search = $request->search;
        $supplier = $request->supplier;

        return $query->when($search, fn($q) => $q->where('VNAME', 'ilike', "%{$search}%"))
            ->when($supplier, fn($q) => $q->where('ISUPPLIER_ID', $supplier))
            ->where('VMETHOD_ID', 'ilike', "%$method%")
            ->where('VNAME', '!=', null);
    }

    #[Scope]
    protected function specific(Builder $query, $data)
    {
        $supplier = $data['supplier'] ?? null;
        $userSupplier = $data['user_supplier'] ?? null;

        if (empty($supplier) || empty($userSupplier)) {
            return $query;
        }

        if (is_numeric($supplier) && is_numeric($userSupplier)) {
            return $query
                ->where('ISUPPLIER_ID', $supplier)
                ->where('IID', $userSupplier);
        }

        return $query
            ->where('VSUPPLIER_CODE', $supplier)
            ->where(function ($q) use ($userSupplier) {
                $q->where('VUSERNAME', $userSupplier)
                    ->orWhere('VNAME', $userSupplier);
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
